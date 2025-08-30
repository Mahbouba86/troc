<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\MessageRepository;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\Annonce\AnnonceStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/message')]
class MessageController extends AbstractController
{
    /**
     * Inbox (liste des conversations)
     */
    #[Route('/messages', name: 'app_message_inbox', methods: ['GET'])]
    public function inbox(
        MessageRepository $repo,
        AnnonceRepository $annonceRepo,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $me */
        $me = $security->getUser();

        $qb = $em->createQueryBuilder()
            ->select('m, a, s, r')
            ->from(Message::class, 'm')
            ->join('m.annonce', 'a')
            ->join('m.sender', 's')
            ->join('m.receiver', 'r')
            ->where('s = :me OR r = :me')
            ->setParameter('me', $me)
            ->orderBy('m.createdAt', 'DESC');

        $all = $qb->getQuery()->getResult();

        // Regroupement (annonce, other) -> dernier message
        $threads = [];
        foreach ($all as $m) {
            $other = $m->getSender()->getId() === $me->getId() ? $m->getReceiver() : $m->getSender();
            $key = $m->getAnnonce()->getId() . '#' . $other->getId();
            if (!isset($threads[$key]) || $m->getCreatedAt() > $threads[$key]['lastAt']) {
                $threads[$key] = [
                    'annonce' => $m->getAnnonce(),
                    'other'   => $other,
                    'last'    => $m,
                    'lastAt'  => $m->getCreatedAt(),
                ];
            }
        }

        return $this->render('message/inbox.html.twig', [
            'threads' => $threads,
        ]);
    }

    /**
     * Page discussion (header annonce + boutons + flux messages + champ envoi)
     * Paramètre optionnel withId: l’autre participant
     */
    #[Route(
        '/{id}/{withId?}',
        name: 'app_message_index',
        methods: ['GET'],
        requirements: ['id' => '\d+', 'withId' => '\d+']
    )]
    public function show(
        Annonce $annonce,
        Request $request,
        MessageRepository $messageRepo,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        /** @var User $me */
        $me = $security->getUser();
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Déterminer l’interlocuteur "other"
        $withId = $request->attributes->get('withId');
        if ($withId) {
            $other = $em->getRepository(User::class)->find((int) $withId);
            if (!$other) {
                throw $this->createNotFoundException('Interlocuteur introuvable');
            }
        } else {
            // Par défaut : si je ne suis pas le propriétaire, je parle au propriétaire
            $other = ($me->getId() === $annonce->getUser()->getId()) ? null : $annonce->getUser();
        }

        // Historique
        $messages = [];
        if ($other) {
            $messages = $messageRepo->findByAnnonceAndUsers($annonce, $me, $other);
        }

        // Formulaire d’envoi (action vers app_message_send)
        $message = new Message();
        $form = $this->createForm(MessageType::class, $message, [
            'action' => $this->generateUrl('app_message_send', [
                'id'   => $annonce->getId(),
                'toId' => $other?->getId() ?? 0, // pas affiché si $other null
            ]),
            'method' => 'POST',
        ]);

        // Tokens CSRF pour actions (réserver/donner/confirmer/annuler)
        $csrfManager = $this->container->get('security.csrf.token_manager');
        $csrfSend = $csrfManager->getToken('send_msg')->getValue();
        $csrfOps  = $csrfManager->getToken('chat_ops')->getValue();

        return $this->render('message/index.html.twig', [
            'annonce'   => $annonce,
            'me'        => $me,
            'other'     => $other, // peut être null si proprio sans withId
            'messages'  => $messages,
            'form'      => $form->createView(),
            'csrf_send' => $csrfSend,
            'csrf_ops'  => $csrfOps,
        ]);
    }

    /**
     * Envoi d’un message (POST).
     */
    #[Route(
        '/send/{id}/{toId}',
        name: 'app_message_send',
        methods: ['POST'],
        requirements: ['id' => '\d+', 'toId' => '\d+']
    )]
    public function send(
        Annonce $annonce,
        int $toId,
        Request $request,
        MessageRepository $messageRepo,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        /** @var User $me */
        $me = $security->getUser();
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // CSRF si formulaire custom
        $token = $request->request->get('_token');
        if ($token && !$this->isCsrfTokenValid('send_msg', $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $to = $em->getRepository(User::class)->find($toId);
        if (!$to) {
            $this->addFlash('danger', 'Destinataire introuvable.');
            return $this->redirectToRoute('app_message_index', ['id' => $annonce->getId()]);
        }

        // Si MessageType utilisé
        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->setSender($me);
            $message->setReceiver($to);
            $message->setAnnonce($annonce);
            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('app_message_index', [
                'id'     => $annonce->getId(),
                'withId' => $to->getId(),
            ]);
        }

        // Fallback simple input name="content"
        $content = trim((string) $request->request->get('content', ''));
        if ($content !== '') {
            $msg = new Message();
            $msg->setSender($me);
            $msg->setReceiver($to);
            $msg->setAnnonce($annonce);
            $msg->setContent($content);
            $em->persist($msg);
            $em->flush();
        }

        return $this->redirectToRoute('app_message_index', [
            'id'     => $annonce->getId(),
            'withId' => $to->getId(),
        ]);
    }

    /**
     * Boutons d’actions sur l’annonce depuis la discussion.
     */
    #[Route(
        '/op/{id}',
        name: 'app_message_op',
        methods: ['POST'],
        requirements: ['id' => '\d+']
    )]
    public function operate(
        Annonce $annonce,
        Request $request,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        /** @var User $me */
        $me = $security->getUser();
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('chat_ops', $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $op     = (string) $request->request->get('op');   // reserve|cancel|give|confirm
        $withId = (int) $request->request->get('withId');  // interlocuteur
        $other  = $em->getRepository(User::class)->find($withId);

        if (!$other) {
            $this->addFlash('danger', 'Interlocuteur introuvable.');
            return $this->redirectToRoute('app_message_index', ['id' => $annonce->getId()]);
        }

        // Récupère état actuel (string ou enum -> on force en string)
        $status = \is_object($annonce->getStatus()) && property_exists($annonce->getStatus(), 'value')
            ? $annonce->getStatus()->value
            : $annonce->getStatus();

        switch ($op) {
            case 'reserve':
                if ($me->getId() !== $annonce->getUser()->getId() && $status === AnnonceStatus::AVAILABLE->value) {
                    $annonce->setStatus(AnnonceStatus::RESERVED->value);
                    if (method_exists($annonce, 'setReservedBy')) {
                        $annonce->setReservedBy($me);
                    }
                }
                break;

            case 'cancel':
                if (method_exists($annonce, 'getReservedBy') && $annonce->getReservedBy()
                    && $annonce->getReservedBy()->getId() === $me->getId()
                    && \in_array($status, [AnnonceStatus::RESERVED->value, AnnonceStatus::PENDING_CONFIRMATION->value], true)) {
                    $annonce->setStatus(AnnonceStatus::AVAILABLE->value);
                    $annonce->setReservedBy(null);
                }
                break;

            case 'give':
                if ($me->getId() === $annonce->getUser()->getId()
                    && $status === AnnonceStatus::RESERVED->value
                    && method_exists($annonce, 'getReservedBy')
                    && $annonce->getReservedBy()
                    && $annonce->getReservedBy()->getId() === $other->getId()) {
                    $annonce->setStatus(AnnonceStatus::PENDING_CONFIRMATION->value);
                }
                break;

            case 'confirm':
                if (method_exists($annonce, 'getReservedBy') && $annonce->getReservedBy()
                    && $annonce->getReservedBy()->getId() === $me->getId()
                    && $status === AnnonceStatus::PENDING_CONFIRMATION->value) {
                    $annonce->setStatus(AnnonceStatus::FINISHED->value);
                }
                break;
        }

        $em->flush();

        return $this->redirectToRoute('app_message_index', [
            'id'     => $annonce->getId(),
            'withId' => $other->getId(),
        ]);
    }
}
