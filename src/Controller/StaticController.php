<?php
// src/Controller/StaticController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentions(): Response
    {
        return $this->render('static/mentions_legales.html.twig');
    }

    #[Route('/conditions-utilisation', name: 'app_terms')]
    public function terms(): Response
    {
        return $this->render('static/terms.html.twig');
    }
}
