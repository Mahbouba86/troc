<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notification;
use App\Enum\Annonce\AnnonceStatus;
use App\Form\UserProfileType;
use App\Repository\AnnonceRepository;
use App\Repository\MessageRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\Reservation\ReservationStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserProfileController extends AbstractController
{
    /**
     * Profil PUBLIC d'un utilisateur (visible par tout le monde).
     * Si l'utilisateur connecté visite sa propre page publique, on redirige vers /profil.
     */
    #[Route('/utilisateur/{id}', name: 'user_profile', methods: ['GET'])]
    public function publicProfile(User $user, AnnonceRepository $annonceRepository): Response
    {
        // Si c'est moi, je vais sur mon profil privé
        if ($this->getUser() && $this->getUser() === $user) {
            return $this->redirectToRoute('app_profile');
        }

        $annonces = $annonceRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        // Stats publiques
        $trocEnCoursCount  = $annonceRepository->countByStatus($user, AnnonceStatus::RESERVED->value);
        $trocRealisesCount = $annonceRepository->countByStatus($user, AnnonceStatus::FINISHED->value);

        return $this->render('user_profile/public.html.twig', [
            'user'              => $user,
            'annonces'          => $annonces,
            'trocEnCoursCount'  => $trocEnCoursCount,
            'trocRealisesCount' => $trocRealisesCount,
        ]);
    }

    /**
     * Mon profil PRIVÉ (nécessite connexion).
     */
    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    public function myProfile(
        MessageRepository $messageRepository,
        AnnonceRepository $annonceRepository,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $me */
        $me = $this->getUser();

        // Mes annonces (je suis propriétaire)
        $annonces = $annonceRepository->findBy(
            ['user' => $me],
            ['createdAt' => 'DESC']
        );

        // Stats perso
        $messagesRecus     = $messageRepository->countReceivedForUser($me);
        $trocEnCoursCount  = $annonceRepository->countByStatus($me, AnnonceStatus::RESERVED->value);
        $trocRealisesCount = $annonceRepository->countByStatus($me, AnnonceStatus::FINISHED->value);

        // Demandes de réservation EN ATTENTE reçues (sur MES annonces)
        $reservationsEnAttente = $reservationRepository->findPendingForOwner($me);

        // MES demandes envoyées (actives = Pending/Accepted)
        $activeValues = [ReservationStatus::PENDING->value, ReservationStatus::ACCEPTED->value];
        $mesReservations = $reservationRepository->createQueryBuilder('r')
            ->innerJoin('r.annonce', 'a')->addSelect('a')
            ->andWhere('r.user = :me')
            ->andWhere('r.statut IN (:st)')
            ->setParameter('me', $me)
            ->setParameter('st', $activeValues)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Finalisations à confirmer par MOI (réceptionneur)
        // - a.finishRequestedAt IS NOT NULL
        // - je suis a.reservedBy OU j'ai une réservation sur a (r.user = moi)
        $finalisationsAConfirmer = $annonceRepository->createQueryBuilder('a')
            ->leftJoin('a.reservations', 'r')
            ->andWhere('a.finishRequestedAt IS NOT NULL')
            ->andWhere('(a.reservedBy = :me OR r.user = :me)')
            ->setParameter('me', $me)
            ->distinct() // évite les doublons sans GROUP BY
            ->orderBy('a.finishRequestedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Notifications de l'utilisateur (les 20 dernières)
        $notifications = $em->getRepository(Notification::class)
            ->findBy(['user' => $me], ['createdAt' => 'DESC'], 20);

        return $this->render('user_profile/index.html.twig', [
            'user'                      => $me,
            'annonces'                  => $annonces,
            'messagesRecus'             => $messagesRecus,
            'trocEnCoursCount'          => $trocEnCoursCount,
            'trocRealisesCount'         => $trocRealisesCount,
            'reservationsEnAttente'     => $reservationsEnAttente,
            'mesReservations'           => $mesReservations,
            'finalisationsAConfirmer'   => $finalisationsAConfirmer,
            'notifications'             => $notifications,
        ]);
    }

    /**
     * Édition de mon profil (PRIVÉ).
     */
    #[Route('/profil/modifier', name: 'app_profile_edit', methods: ['GET','POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $me */
        $me = $this->getUser();

        $form = $this->createForm(UserProfileType::class, $me);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user_profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
