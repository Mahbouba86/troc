<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\Category;
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

        // ➕ Si une catégorie est passée via l'URL (clic sur image)
        $categoryId = $request->query->get('category');
        if ($categoryId) {
            $qb->andWhere('a.category = :cat')
                ->setParameter('cat', $categoryId);
        }

        // ➕ Si le formulaire est soumis
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

        // 🔁 Résultats
        $annonces = $qb->orderBy('a.createdAt', 'DESC')->getQuery()->getResult();

        // 📦 Récupérer toutes les catégories pour affichage en haut
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
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $em): Response
    {
        // Optionnel : sécurité pour n’autoriser que l’auteur à modifier
        if ($annonce->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Annonce modifiée avec succès.');
            return $this->redirectToRoute('mes_annonces');
        }

        return $this->render('annonce/edit.html.twig', [
            'form' => $form,
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
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }

                $annonce->setImage($newFilename);
            }

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
