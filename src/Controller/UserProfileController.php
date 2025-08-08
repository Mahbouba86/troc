<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileType;
use App\Repository\AnnonceRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Enum\Annonce\Status\AnnonceStatus;
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

        // Stats publiques (adapte si tu veux masquer)
        $trocEnCoursCount  = $annonceRepository->countByStatus($user, AnnonceStatus::RESERVED->value);
        $trocRealisesCount = $annonceRepository->countByStatus($user, AnnonceStatus::FINISHED->value);

        return $this->render('user_profile/public.html.twig', [
            'user'               => $user,
            'annonces'           => $annonces,
            'trocEnCoursCount'   => $trocEnCoursCount,
            'trocRealisesCount'  => $trocRealisesCount,
            // pas de messagesRecus en public
        ]);
    }

    /**
     * Mon profil PRIVÉ (nécessite connexion).
     */
    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    public function myProfile(
        MessageRepository $messageRepository,
        AnnonceRepository $annonceRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $me */
        $me = $this->getUser();

        $annonces = $annonceRepository->findBy(
            ['user' => $me],
            ['createdAt' => 'DESC']
        );

        $messagesRecus     = $messageRepository->countReceivedForUser($me);
        $trocEnCoursCount  = $annonceRepository->countByStatus($me, AnnonceStatus::RESERVED->value);
        $trocRealisesCount = $annonceRepository->countByStatus($me, AnnonceStatus::FINISHED->value);

        return $this->render('user_profile/index.html.twig', [
            'user'               => $me,
            'annonces'           => $annonces,
            'messagesRecus'      => $messagesRecus,
            'trocEnCoursCount'   => $trocEnCoursCount,
            'trocRealisesCount'  => $trocRealisesCount,
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
