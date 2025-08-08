<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\Category;
use App\Entity\Photo;
use App\Form\AnnonceType;
use App\Form\SearchAnnonceType;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class AnnonceController extends AbstractController
{
    #[Route('/annonces', name: 'annonce_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SearchAnnonceType::class);
        $form->handleRequest($request);

        $qb = $em->getRepository(Annonce::class)->createQueryBuilder('a');

        $categoryId = $request->query->get('category');
        if ($categoryId) {
            $qb->andWhere('a.category = :cat')->setParameter('cat', $categoryId);
        }

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
        $categories = $em->getRepository(Category::class)->findAll();

        return $this->render('annonce/index.html.twig', [
            'form' => $form->createView(),
            'annonces' => $annonces,
            'categories' => $categories,
        ]);
    }

    #[Route('/annonce/{id}', name: 'annonce_show', requirements: ['id' => '\d+'])]
    public function show(Annonce $annonce): Response
    {
        return $this->render('annonce/show.html.twig', [
            'annonce' => $annonce,
        ]);
    }

    #[Route('/mes-annonces', name: 'mes_annonces')]
    public function mesAnnonces(AnnonceRepository $annonceRepository, Security $security): Response
    {
        $user = $security->getUser();
        $annonces = $annonceRepository->findBy(['user' => $user]);

        return $this->render('annonce/mes_annonces.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/annonce/{id}/edit', name: 'annonce_edit')]
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($annonce->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFiles = $form->get('photos')->getData();

            foreach ($uploadedFiles as $uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload d\'une image.');
                    continue;
                }

                $photo = new Photo();
                $photo->setFilename($newFilename);
                $photo->setAnnonce($annonce);

                $em->persist($photo);
            }

            $em->flush();
            $this->addFlash('success', 'Annonce modifiée avec succès.');
            return $this->redirectToRoute('mes_annonces');
        }

        return $this->render('annonce/edit.html.twig', [
            'form' => $form->createView(),
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
            $annonce->setUser($this->getUser());
            $annonce->setCreatedAt(new \DateTimeImmutable());

            $uploadedFiles = $form->get('photos')->getData();
            $first = true;

            foreach ($uploadedFiles as $uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                    continue;
                }

                $photo = new Photo();
                $photo->setFilename($newFilename);
                $photo->setAnnonce($annonce);

                // Marquer la première comme principale plus tard si besoin
                $em->persist($photo);
                $first = false;
            }

            $em->persist($annonce);
            $em->flush();

            $this->addFlash('success', 'Annonce créée avec succès.');
            return $this->redirectToRoute('annonce_new');
        }

        return $this->render('annonce/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

