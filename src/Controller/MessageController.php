<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\Message;
use App\Form\MessageType;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route; // ← NE PAS CONFONDRE AVEC Attribute\Route
use Symfony\Bundle\SecurityBundle\Security;

class MessageController extends AbstractController
{
    #[Route('/message/{id}', name: 'app_message_index')]
    public function show(
        Annonce $annonce,
        Request $request,
        MessageRepository $messageRepo,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        $user = $security->getUser();
        $nbMessagesRecus = $messageRepo->countReceivedForUser($user);

        $receiver = $annonce->getUser(); // propriétaire de l'annonce

        $messages = $messageRepo->findByAnnonceAndUsers($annonce, $user, $receiver);

        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->setSender($user);
            $message->setReceiver($receiver);
            $message->setAnnonce($annonce);
            $message->setCreatedAt(new \DateTime());

            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('app_message_index', ['id' => $annonce->getId()]);
        }

        return $this->render('message/index.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView(),
            'messages' => $messages,
        ]);
    }
}
