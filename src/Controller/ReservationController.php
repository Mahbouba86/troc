<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\Reservation;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\Reservation\ReservationStatus;            // enum de la RÉSERVATION
use App\Enum\Annonce\AnnonceStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('', name: 'reservation_index', methods: ['GET'])]
    #[Route('/', name: 'reservation_index_slash', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_profile');
    }

    /**
     * Crée une demande de réservation (Pending) pour une annonce.
     */
    #[Route('/request/{id}', name: 'reservation_request', methods: ['POST'])]
    public function request(
        Annonce $annonce,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // CSRF
        if (!$this->isCsrfTokenValid('reservation_request_' . $annonce->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Empêche de réserver sa propre annonce
        if ($annonce->getUser() === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas réserver votre propre annonce.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        // Empêche la réservation si l’annonce est déjà marquée “RESERVED”
        if (method_exists($annonce, 'getStatus') && $annonce->getStatus() === AnnonceStatus::RESERVED) {
            $this->addFlash('warning', 'Cette annonce est déjà réservée.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        // Empêche les doublons ACTIFS (Pending/Accepted) pour (user, annonce)
        $activeValues = [ReservationStatus::PENDING->value, ReservationStatus::ACCEPTED->value];
        $countActive = (int) $em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.annonce = :annonce')
            ->andWhere('r.statut IN (:active)')
            ->setParameter('user', $this->getUser())
            ->setParameter('annonce', $annonce)
            ->setParameter('active', $activeValues)
            ->getQuery()
            ->getSingleScalarResult();

        if ($countActive > 0) {
            $this->addFlash('warning', 'Vous avez déjà une demande en cours pour cette annonce.');
            return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
        }

        // Crée la réservation Pending
        $reservation = new Reservation();
        $reservation->setAnnonce($annonce);
        $reservation->setUser($this->getUser());
        // (Optionnel si géré dans __construct())
        $reservation->setCreatedAt(new \DateTimeImmutable());
        $reservation->setStatut(ReservationStatus::PENDING);

        $em->persist($reservation);
        $em->flush();

        // Notifie le propriétaire
        $notificationService->notify(
            $annonce->getUser(),
            'Nouvelle demande de réservation',
            sprintf(
                '%s souhaite réserver votre annonce « %s ».',
                $this->getUser()->getUserIdentifier(),
                $annonce->getTitre()
            )
        );

        $this->addFlash('success', 'Demande envoyée. Le propriétaire décidera d’accepter ou de refuser.');
        return $this->redirectToRoute('annonce_show', ['id' => $annonce->getId()]);
    }

    /**
     * Accepter une demande -> statut = Accepted (réservation)
     * (+ optionnel : l’annonce passe à RESERVED).
     */
    #[Route('/accept/{id}', name: 'reservation_accept', methods: ['POST'])]
    public function accept(
        Reservation $reservation,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->isCsrfTokenValid('reservation_accept_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($reservation->getAnnonce()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Action non autorisée');
        }

        // MAJ des statuts
        $reservation->setStatut(ReservationStatus::ACCEPTED);
        if (method_exists($reservation->getAnnonce(), 'setStatus')) {
            $reservation->getAnnonce()->setStatus(AnnonceStatus::RESERVED); // cohérent côté annonce
        }
        $em->flush();

        // Lien discussion
        $chatUrl = $this->generateUrl(
            'app_message_index',
            ['id' => $reservation->getAnnonce()->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Notifie le demandeur
        $notificationService->notify(
            $reservation->getUser(),
            'Demande de réservation acceptée',
            sprintf(
                'Votre demande pour « %s » a été acceptée. Vous pouvez échanger avec le propriétaire ici : %s',
                $reservation->getAnnonce()->getTitre(),
                $chatUrl
            )
        );

        $this->addFlash('success', 'Demande acceptée.');
        return $this->redirectToRoute('app_profile');
    }

    /**
     * Refuser une demande -> statut = Refused.
     */
    #[Route('/decline/{id}', name: 'reservation_decline', methods: ['POST'])]
    public function decline(
        Reservation $reservation,
        EntityManagerInterface $em,
        NotificationService $notificationService,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->isCsrfTokenValid('reservation_decline_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($reservation->getAnnonce()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Action non autorisée');
        }

        // MAJ statut réservation
        $reservation->setStatut(ReservationStatus::REFUSED);
        $em->flush();

        // Lien discussion
        $chatUrl = $this->generateUrl(
            'app_message_index',
            ['id' => $reservation->getAnnonce()->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Notifie le demandeur
        $notificationService->notify(
            $reservation->getUser(),
            'Demande de réservation refusée',
            sprintf(
                'Votre demande pour « %s » a été refusée. Vous pouvez discuter avec le propriétaire pour trouver une alternative : %s',
                $reservation->getAnnonce()->getTitre(),
                $chatUrl
            )
        );

        $this->addFlash('info', 'Demande refusée.');
        return $this->redirectToRoute('app_profile');
    }
}
