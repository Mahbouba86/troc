<?php

namespace App\Controller;

use App\Form\UserProfileType;
use App\Repository\AnnonceRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;


class UserProfileController extends AbstractController
{
    #[Route('/utilisateur/{id}', name: 'user_profile')]
    public function publicProfile(User $user, AnnonceRepository $annonceRepository): Response
    {
        $annonces = $annonceRepository->findBy(['user' => $user]);

        return $this->render('user_profile/index.html.twig', [
            'user' => $user,
            'annonces' => $annonces,
        ]);
    }
    #[Route('/profil', name: 'app_profile')]
    public function index(
        MessageRepository $messageRepository,
        AnnonceRepository $annonceRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Récupération des compteurs
        $messagesRecus = $messageRepository->countReceivedForUser($user);
        $trocEnCoursCount = $annonceRepository->countByStatus($user, 'Réservé');
        $trocRealisesCount = $annonceRepository->countByStatus($user, 'Troc effectué');

        return $this->render('user_profile/index.html.twig', [
            'user' => $user,
            'messagesRecus' => $messagesRecus,
            'trocEnCoursCount' => $trocEnCoursCount,
            'trocRealisesCount' => $trocRealisesCount,
        ]);
    }


    #[Route('/profil/modifier', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        $form = $this->createForm(UserProfileType::class, $user);
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
