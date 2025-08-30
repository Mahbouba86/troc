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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Enum\Annonce\AnnonceStatus;

// UX Map (Leaflet)
use Symfony\UX\Map\Circle;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Icon\Icon;
use Symfony\UX\Map\Rectangle;

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

        // Filtre rapide par catégorie via query ?category=ID
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

    /**
     * Recherche AJAX (retourne un fragment HTML)
     * GET /annonces/search?q=velo&category=1&ville=Paris
     */
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
            // Valeurs "actives" de l'Annonce
            $activeValues = ['PENDING', 'RESERVED'];
            if (class_exists(AnnonceStatus::class)) {
                try {
                    $activeValues = [
                        AnnonceStatus::PENDING->value,
                        AnnonceStatus::RESERVED->value,
                    ];
                } catch (\Throwable) {
                    // fallback
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

        $geogouv = null; // $this->geocoderService->geocodeCity($annonce->getVille());
        if (null === $geogouv) {
            $geogouv = ['lat' => 48.866667, 'lon' => 2.333333];
        }

        // ---------------------
        // Carte UX Map (Leaflet)
        // ---------------------
        $map = new Map();

        // TODO: Remplacer par lat/lng de l’annonce quand disponibles en BDD
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

    #[Route('/annonce/{id}/edit', name: 'annonce_edit')]
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($annonce->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1) Upload de nouvelles photos (optionnel)
            $uploadedFiles = $form->get('photos')->getData() ?? [];
            foreach ($uploadedFiles as $uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = (string)$slugger->slug((string)$originalFilename);
                $ext = $uploadedFile->guessExtension() ?: 'bin';
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $ext;

                try {
                    $uploadedFile->move($this->getParameter('uploads_directory'), $newFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'Erreur lors de l\'upload d\'une image.');
                    continue;
                }

                $photo = new Photo();
                $photo->setFilename($newFilename);
                $photo->setAnnonce($annonce);
                $em->persist($photo);
            }

            // 2) Déterminer/mettre à jour la photo principale
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

    #[Route('/annonce/new', name: 'annonce_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $annonce = new Annonce();

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $annonce->setUser($this->getUser());
            $annonce->setCreatedAt(new \DateTimeImmutable());

            // 1) Upload des photos
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
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                    continue;
                }

                $photo = new Photo();
                $photo->setFilename($newFilename);
                $photo->setAnnonce($annonce);
                $em->persist($photo);

                $newPhotos[] = $photo;
            }

            $em->persist($annonce);
            $em->flush(); // IDs des photos

            // 2) Déterminer la principale à partir de la radio
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
}
