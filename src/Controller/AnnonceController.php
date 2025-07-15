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
use App\Form\SearchAnnonceType;

class AnnonceController extends AbstractController
{
    #[Route('/annonces', name: 'annonce_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        // Création du formulaire
        $form = $this->createForm(SearchAnnonceType::class);
        $form->handleRequest($request);

        $qb = $em->getRepository(Annonce::class)->createQueryBuilder('a');

        // Si le formulaire est soumis
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

        return $this->render('annonce/index.html.twig', [
            'form' => $form->createView(),
            'annonces' => $annonces,
        ]);
    }


    #[Route('/annonce/{id}', name: 'annonce_show', requirements: ['id' => '\d+'])]
    public function show(Annonce $annonce): Response
    {
        return $this->render('annonce/show.html.twig', [
            'annonce' => $annonce,
        ]);
    }

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
