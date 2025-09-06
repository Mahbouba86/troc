<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Form\ContactMessageReplyType;
use App\Service\MailerService\MailerService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Request;

class ContactMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('sujet', 'Sujet')
                ->setFormTypeOption('disabled', true), // Lecture seulement pour l'admin

            TextField::new('email', 'Email')
                ->setFormTypeOption('disabled', true), // Lecture seule

            TextareaField::new('message', 'Message')
                ->setFormTypeOption('disabled', true)
                ->hideOnIndex(),
        ];
    }


    public function configureActions(Actions $actions): Actions
    {
        $viewInvoice = Action::new('sendEmail', 'Répondre au message')
            ->linkToCrudAction('sendEmail');

        return $actions
            ->add(Crud::PAGE_INDEX, Crud::PAGE_DETAIL)
            ->add(Crud::PAGE_INDEX, $viewInvoice);
    }

    #[AdminAction(routePath: '/send_mail', routeName: 'send_mail', methods: ['GET', 'POST'])]
    public function sendEmail(AdminContext $context, Request $request, MailerService $mailerService)
    {
        /** @var ContactMessage $message */
        $message = $context->getEntity()->getInstance();
        $form = $this->createForm(ContactMessageReplyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reply = $form->get('reply')->getData();
            $sender = $context->getUser()->getEmail();
            $recipent = $message->getEmail();
            $mailerService->sendEmail($sender, $recipent, 'email de test', $reply); // this method should be in your MailerService
            $this->addFlash('success', 'Email envoyé avec succès !');


            // Redirect back to the detail page
            return $this->getRedirectResponseAfterSave($context, 'index');
        }

        return $this->render('admin/contactMessage/reply.html.twig', [
            'form' => $form->createView(),
            'contactMessage' => $message,
        ]);
    }
}

