<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileType;
use App\Repository\AnnonceRepository;
use App\Repository\MessageRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Enum\Annonce\Status\AnnonceStatus;
use Enum\Reservation\ReservationStatus; // ✅ pour filtrer Pending/Accepted
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
        ReservationRepository $reservationRepository
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

        // ✅ Demandes de réservation EN ATTENTE reçues (sur MES annonces)
        $reservationsEnAttente = $reservationRepository->findPendingForOwner($me);

        // ✅ MES demandes envoyées (actives = Pending/Accepted)
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

        return $this->render('user_profile/index.html.twig', [
            'user'                  => $me,
            'annonces'              => $annonces,
            'messagesRecus'         => $messagesRecus,
            'trocEnCoursCount'      => $trocEnCoursCount,
            'trocRealisesCount'     => $trocRealisesCount,
            'reservationsEnAttente' => $reservationsEnAttente, // demandes reçues (proprio)
            'mesReservations'       => $mesReservations,        // ✅ demandes envoyées (demandeur)
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
            'form' => $form,
        ]);
    }
}
