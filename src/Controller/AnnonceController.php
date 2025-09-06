<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Photo;
use App\Form\AnnonceReservationType;
use App\Form\AnnonceType;
use App\Form\SearchAnnonceType;
use App\Repository\AnnonceRepository;
use App\Service\Geocoding\GeoGouvGeocoderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Enum\Annonce\AnnonceStatus;

// UX Map (Leaflet)
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Icon\Icon;

// Notifications & outils
use App\Service\Notification\NotificationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

        // ⬇️ VISIBILITÉ PUBLIQUE : on exclut les annonces en attente (PENDING)
        $qb->andWhere('a.status <> :pendingStatus')
            ->setParameter('pendingStatus', AnnonceStatus::PENDING->value);

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
        $term = (string)$request->query->get('q', '');
        $categoryId = $request->query->getInt('category', 0) ?: null;
        $ville = (string)$request->query->get('ville', '') ?: null;

        // Le repo exclut déjà PENDING.
        $annonces = $repo->searchByTerm($term, $categoryId, $ville);

        return $this->render('annonce/_annonce_cards.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/annonce/{id}', name: 'annonce_show', requirements: ['id' => '\d+'])]
    public function show(Annonce $annonce): Response
    {
        $user = $this->getUser();
        $isOwner = $user && $annonce->getUser() && $annonce->getUser()->getId() === $user->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isVisible = $annonce->getStatus() !== AnnonceStatus::PENDING;

        // ⬇️ Page détail visible si non-PENDING, ou admin, ou auteur.
        if (!$isVisible && !$isAdmin && !$isOwner) {
            throw $this->createNotFoundException();
        }

        $hasMyActiveReservation = false;

        if ($user) {
            $activeReservationCodes = ['PENDING', 'RESERVED'];

            foreach ($annonce->getReservations() as $r) {
                if ($r->getUser() === $user) {
                    $code = $this->enumCode($r->getStatut());
                    if (\in_array($code, $activeReservationCodes, true)) {
                        $hasMyActiveReservation = true;
                        break;
                    }
                }
            }
        }

        $geogouv = ['lat' => 48.866667, 'lon' => 2.333333];

        $map = new Map();
        $icon = Icon::url('p/uploads/icones/pin.png')->width(32)->height(32);
        $point = new Point($geogouv['lat'], $geogouv['lon']);

        $map->addMarker(new Marker(
            position: $point,
            title: $annonce->getTitre(),
            infoWindow: new InfoWindow(
                headerContent: '<b>' . htmlspecialchars($annonce->getTitre(), ENT_QUOTES) . '</b>',
                content: 'Ville : ' . htmlspecialchars((string)$annonce->getVille(), ENT_QUOTES)
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

    #[Route('/annonce/{id}/reservation-edit', name: 'annonce_reserveration_edit', methods: ['GET', 'POST'])]
    public function reservationEdit(Request $request, Annonce $annonce, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ANNONCE_EDIT', $annonce);

        if ($annonce->getUser() !== $this->getUser() || (
                $annonce->getStatus() !== AnnonceStatus::AVAILABLE &&
                $annonce->getStatus() !== AnnonceStatus::RESERVED
            )) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(AnnonceReservationType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Statut de l\'annonce modifiée avec succès.');

            return $this->redirectToRoute('mes_annonces');
        }

        return $this->render('annonce/reservation_edit.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
        ]);
    }

    #[Route('/annonce/{id}/edit', name: 'annonce_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ANNONCE_EDIT', $annonce);

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
            $fs = new Filesystem();
            $uploadDir = $this->getParameter('uploads_directory');
            $path = $uploadDir . '/' . $photo->getFilename();
            if ($fs->exists($path)) {
                try {
                    $fs->remove($path);
                } catch (\Throwable) {
                }
            }

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
            $annonce->setStatus(AnnonceStatus::PENDING);

            $em->persist($annonce);
            $em->flush();

            $mainPhotoId = $request->request->get('mainPhoto');
            if ($mainPhotoId) {
                $annonce->setMainPhotoById((int)$mainPhotoId);
            } elseif (\count($newPhotos) > 0) {
                $newPhotos[0]->setIsMain(true);
            }

            $em->flush();

            $this->addFlash('success', 'Annonce créée et envoyée en validation.');
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

        $fs = new Filesystem();
        $uploadDir = $this->getParameter('uploads_directory');
        foreach ($annonce->getPhotos() as $photo) {
            $path = $uploadDir . '/' . $photo->getFilename();
            if ($fs->exists($path)) {
                try {
                    $fs->remove($path);
                } catch (\Throwable) {
                }
            }
            $em->remove($photo);
        }

        $em->remove($annonce);
        $em->flush();

        $this->addFlash('success', 'Annonce supprimée avec succès.');
        return $this->redirectToRoute('mes_annonces');
    }

    /* ===========================
       Finalisation depuis discussion
       =========================== */

    /**
     * Le propriétaire déclenche la demande de finalisation.
     */
    #[Route('/discussion/{id}/request-finish', name: 'discussion_request_finish', methods: ['POST'])]
    public function requestFinish(
        Request                $request,
        Annonce                $annonce,
        EntityManagerInterface $em,
        NotificationService    $notifier
    ): Response
    {
        if ($annonce->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('request_finish_' . $annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        // 1) bénéficiaire explicite
        $receiver = $annonce->getReservedBy();

        // 2) sinon, réservation au statut RESERVED (si tu l’utilises)
        if (!$receiver) {
            foreach ($annonce->getReservations() as $r) {
                $st = $r->getStatut();
                $code = $st instanceof \BackedEnum ? $st->value : ($st instanceof \UnitEnum ? $st->name : (string)$st);
                if (strtoupper($code) === 'RESERVED') {
                    $receiver = $r->getUser();
                    break;
                }
            }
        }

        // 3) repli : interlocuteur actif passé en hidden (receiverId)
        if (!$receiver) {
            $receiverId = (int)$request->request->get('receiverId', 0);
            if ($receiverId > 0) {
                $candidate = $em->getRepository(User::class)->find($receiverId);
                if ($candidate && $candidate !== $this->getUser()) {
                    $receiver = $candidate;
                }
            }
        }

        if (!$receiver) {
            $this->addFlash('warning', 'Aucun réceptionneur trouvé pour cette annonce.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        if (method_exists($annonce, 'setFinishRequestedAt')) {
            $annonce->setFinishRequestedAt(new \DateTimeImmutable());
        }
        $em->flush();

        $url = $this->generateUrl('annonce_show', ['id' => $annonce->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $notifier->send(
            $receiver,
            'Confirmez la réception',
            sprintf('Le propriétaire indique que « %s » est remis. Ouvrez la discussion pour confirmer.', (string)$annonce->getTitre()),
            $url
        );

        $this->addFlash('info', 'Demande envoyée. En attente de confirmation du réceptionneur.');
        return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
    }

    /**
     * Le réceptionneur confirme la réception -> statut FINISHED + notification propriétaire.
     */
    #[Route('/discussion/{id}/confirm-finish', name: 'discussion_confirm_finish', methods: ['POST'])]
    public function confirmFinish(
        Request                $request,
        Annonce                $annonce,
        EntityManagerInterface $em,
        NotificationService    $notifier
    ): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // autorisation : reservedBy = moi, ou j'ai une réservation sur cette annonce
        $isReceiver = false;

        if ($annonce->getReservedBy() && $annonce->getReservedBy()->getId() === $user->getId()) {
            $isReceiver = true;
        } else {
            foreach ($annonce->getReservations() as $r) {
                if ($r->getUser() && $r->getUser()->getId() === $user->getId()) {
                    $isReceiver = true;
                    break;
                }
            }
        }

        if (!$isReceiver) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('confirm_finished_' . $annonce->getId() . '_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        $annonce->setStatus(AnnonceStatus::FINISHED);

        if (method_exists($annonce, 'setFinishRequestedAt')) {
            $annonce->setFinishRequestedAt(null);
        }

        $em->flush();

        $notifier->send(
            $annonce->getUser(),
            'Réception confirmée',
            sprintf('%s a confirmé la réception de « %s ».', $user->getUserIdentifier(), (string)$annonce->getTitre()),
            $this->generateUrl('annonce_show', ['id' => $annonce->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
        );

        $this->addFlash('success', 'Réception confirmée. Merci !');
        return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
    }

    /**
     * Retourne le "code" d'un statut (enum -> value|name, string sinon), en UPPER.
     */
    private function enumCode(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return strtoupper((string)$status->value);
        }
        if ($status instanceof \UnitEnum) {
            return strtoupper($status->name);
        }
        return strtoupper((string)$status);
    }
}
