<?php
namespace App\Controller;

use App\Entity\Annonce;
use App\Form\AnnonceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
class AnnonceController extends AbstractController
{
    #[Route('/annonce/new', name: 'annonce_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $annonce = new Annonce();

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l’image si présente
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'), // je dois le  définir dans services.yaml
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }

                $annonce->setImage($newFilename);
            }

            // Auto-remplissage
            $annonce->setUser($this->getUser());
            $annonce->setCreatedAt(new \DateTimeImmutable());

            $em->persist($annonce);
            $em->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('annonce/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
