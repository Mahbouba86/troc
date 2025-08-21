<?php

namespace App\Controller;

use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AnnoncePhotoController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/annonce/{photo}/delete', name: 'annonce_photo_delete')]
    public function delete(Photo $photo)
    {
        $this->entityManager->remove($photo);
        $this->entityManager->flush();

        return new RedirectResponse($this->generateUrl('annonce_edit', ['id' => $photo->getAnnonce()->getId()]));
    }
}
