<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\Category;
use App\Entity\Photo;
use App\Form\AnnonceType;
use App\Form\SearchAnnonceType;
use App\Repository\AnnonceRepository;
use App\Service\Geocoding\GeoGouvGeocoderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Enum\Annonce\AnnonceStatus;

// UX Map (Leaflet)
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Icon\Icon;

class AnnonceController extends AbstractController
{
    public function __construct(private readonly GeoGouvGeocoderService $geocoderService)
    {
    }

    #[Route('/annonces', name: 'annonce_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SearchAnnonceType::class);
        $form->handleRequest($request);

        $qb = $em->getRepository(Annonce::class)->createQueryBuilder('a');

        $categoryId = $request->query->get('category');
        if ($categoryId) {
            $qb->andWhere('a.category = :cat')->setParameter('cat', $categoryId);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if (!empty($data['ville'])) {
                $qb->andWhere('a.ville LIKE :ville')
                    ->setParameter('ville', '%' . $data['ville'] . '%');
            }

            if (!empty($data['category'])) {
                $qb->andWhere('a.category = :category')
                    ->setParameter('category', $data['category']);
            }
        }

        $annonces = $qb->orderBy('a.createdAt', 'DESC')->getQuery()->getResult();
        $categories = $em->getRepository(Category::class)->findAll();

        return $this->render('annonce/index.html.twig', [
            'form' => $form->createView(),
            'annonces' => $annonces,
            'categories' => $categories,
        ]);
    }

    #[Route('/annonces/search', name: 'annonce_search', methods: ['GET'])]
    public function search(Request $request, AnnonceRepository $repo): Response
    {
        $term = (string) $request->query->get('q', '');
        $categoryId = $request->query->getInt('category', 0) ?: null;
        $ville = (string) $request->query->get('ville', '') ?: null;

        $annonces = $repo->searchByTerm($term, $categoryId, $ville);

        return $this->render('annonce/_annonce_cards.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/annonce/{id}', name: 'annonce_show', requirements: ['id' => '\d+'])]
    public function show(Annonce $annonce): Response
    {
        $hasMyActiveReservation = false;
        $user = $this->getUser();

        if ($user) {
            $activeValues = ['PENDING', 'RESERVED'];
            if (class_exists(AnnonceStatus::class)) {
                try {
                    $activeValues = [
                        AnnonceStatus::PENDING->value,
                        AnnonceStatus::RESERVED->value,
                    ];
                } catch (\Throwable) {
                }
            }

            foreach ($annonce->getReservations() as $r) {
                if ($r->getUser() === $user) {
                    $statut = $r->getStatut();
                    $statusValue = (\is_object($statut) && method_exists($statut, 'value')) ? $statut->value : $statut;
                    if (\in_array($statusValue, $activeValues, true)) {
                        $hasMyActiveReservation = true;
                        break;
                    }
                }
            }
        }

        $geogouv = null;
        if (null === $geogouv) {
            $geogouv = ['lat' => 48.866667, 'lon' => 2.333333];
        }

        $map = new Map();
        $icon = Icon::url('p/uploads/icones/pin.png')->width(32)->height(32);
        $point = new Point($geogouv['lat'], $geogouv['lon']);

        $map->addMarker(new Marker(
            position: $point,
            title: $annonce->getTitre(),
            infoWindow: new InfoWindow(
                headerContent: '<b>'.htmlspecialchars($annonce->getTitre(), ENT_QUOTES).'</b>',
                content: 'Ville : '.htmlspecialchars((string) $annonce->getVille(), ENT_QUOTES)
            ),
            icon: $icon
        ));

        $map->fitBoundsToMarkers();

        return $this->render('annonce/show.html.twig', [
            'annonce' => $annonce,
            'hasMyActiveReservation' => $hasMyActiveReservation,
            'map' => $map,
        ]);
    }

    #[Route('/mes-annonces', name: 'mes_annonces')]
    public function mesAnnonces(Security $security, EntityManagerInterface $em): Response
    {
        $user = $security->getUser();
        $annonces = $em->getRepository(Annonce::class)->findBy(['user' => $user]);

        return $this->render('annonce/mes_annonces.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/annonce/{id}/edit', name: 'annonce_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($annonce->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AnnonceType::class, $annonce, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFiles = $form->get('photos')->getData() ?? [];
            foreach ($uploadedFiles as $uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string)$slugger->slug((string)$originalFilename);
                $ext = $uploadedFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $ext;

                try {
                    $uploadedFile->move($this->getParameter('uploads_directory'), $newFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'Upload de l’image impossible.');
                    continue;
                }

                $photo = new Photo();
                $photo->setFilename($newFilename);
                $photo->setAnnonce($annonce);
                $em->persist($photo);
            }

            $mainPhotoId = $request->request->get('mainPhoto');
            if ($mainPhotoId) {
                $annonce->setMainPhotoById((int)$mainPhotoId);
            } elseif (!$annonce->getMainPhoto() && \count($annonce->getPhotos()) > 0) {
                $first = $annonce->getPhotos()->first();
                if ($first instanceof Photo) {
                    $first->setIsMain(true);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Annonce modifiée avec succès.');

            return $this->redirectToRoute('mes_annonces');
        }

        return $this->render('annonce/edit.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
        ]);
    }

    #[Route('/annonce/photo/{photo}/delete', name: 'annonce_photo_delete', methods: ['POST'])]
    public function deletePhoto(Photo $photo, Request $request, EntityManagerInterface $em): Response
    {
        $annonce = $photo->getAnnonce();

        if ($annonce->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_photo_' . $photo->getId(), $request->request->get('_token'))) {
            // Supprime le fichier du disque
            $fs = new Filesystem();
            $uploadDir = $this->getParameter('uploads_directory');
            $path = $uploadDir . '/' . $photo->getFilename();
            if ($fs->exists($path)) {
                try { $fs->remove($path); } catch (\Throwable) {}
            }

            // Supprime en BDD
            $em->remove($photo);
            $em->flush();

            $this->addFlash('success', 'Photo supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('annonce_edit', ['id' => $annonce->getId()]);
    }

    #[Route('/annonce/new', name: 'annonce_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $annonce = new Annonce();

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $annonce->setUser($this->getUser());
            $annonce->setCreatedAt(new \DateTimeImmutable());

            $uploadedFiles = $form->get('photos')->getData() ?? [];
            $newPhotos = [];

            foreach ($uploadedFiles as $uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string)$slugger->slug((string)$originalFilename);
                $ext = $uploadedFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $ext;

                try {
                    $uploadedFile->move($this->getParameter('uploads_directory'), $newFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'Upload de l’image impossible.');
                    continue;
                }

                $photo = new Photo();
                $photo->setFilename($newFilename);
                $photo->setAnnonce($annonce);
                $em->persist($photo);

                $newPhotos[] = $photo;
            }

            $em->persist($annonce);
            $em->flush();

            $mainPhotoId = $request->request->get('mainPhoto');
            if ($mainPhotoId) {
                $annonce->setMainPhotoById((int)$mainPhotoId);
            } elseif (\count($newPhotos) > 0) {
                $newPhotos[0]->setIsMain(true);
            }

            $em->flush();

            $this->addFlash('success', 'Annonce créée avec succès.');
            return $this->redirectToRoute('annonce_edit', ['id' => $annonce->getId()]);
        }

        return $this->render('annonce/new.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
        ]);
    }

    /**
     * Suppression TOTALE d'une annonce (CSRF + vérification propriétaire/admin)
     */
    #[Route('/annonce/{id}/delete', name: 'annonce_delete', methods: ['POST'])]
    public function delete(Request $request, Annonce $annonce, EntityManagerInterface $em): Response
    {
        if ($annonce->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_annonce_' . $annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        // Supprimer les fichiers photos du disque
        $fs = new Filesystem();
        $uploadDir = $this->getParameter('uploads_directory');
        foreach ($annonce->getPhotos() as $photo) {
            $path = $uploadDir . '/' . $photo->getFilename();
            if ($fs->exists($path)) {
                try { $fs->remove($path); } catch (\Throwable) {}
            }
            $em->remove($photo);
        }

        // Supprime l'annonce
        $em->remove($annonce);
        $em->flush();

        $this->addFlash('success', 'Annonce supprimée avec succès.');
        return $this->redirectToRoute('mes_annonces');
    }
}
