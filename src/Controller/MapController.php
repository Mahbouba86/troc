<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

class MapController extends AbstractController
{
    #[Route('/map', name: 'app_map', methods: ['GET'])]
    public function index(): Response
    {
        $map = new Map();
        $map
            // Explicitly set the center and zoom
            ->center(new Point(46.903354, 1.888334))
            ->zoom(6)

            // Or automatically fit the bounds to the markers
            ->fitBoundsToMarkers()

            ->addMarker(new Marker(
                position: new Point(48.8566, 2.3522),
                title: 'Paris'
            ))

            // With an info window associated to the marker:
            ->addMarker(new Marker(
                position: new Point(45.7640, 4.8357),
                title: 'Lyon',
                infoWindow: new InfoWindow(
                    headerContent: '<b>Lyon</b>',
                    content: 'The French town in the historic Rhône-Alpes region, located at the junction of the Rhône and Saône rivers.'
                ),
            ))
        ;

        return $this->render('map/index.html.twig', [
            'map'    => $map,
        ]);
    }
}
