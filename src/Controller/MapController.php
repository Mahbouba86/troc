<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Map\Icon\Icon;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Bridge\Leaflet\Option\AttributionControlOptions;
use Symfony\UX\Map\Bridge\Leaflet\Option\ControlPosition;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;
use Symfony\UX\Map\Bridge\Leaflet\Option\ZoomControlOptions;

class MapController extends AbstractController
{
    #[Route('/map', name: 'app_map', methods: ['GET'])]
    public function index(): Response
    {
        $icon = Icon::ux('fa:map-marker')->width(24)->height(24);

        $map = (new Map())
            ->center(new Point(48.8566, 2.3522))
            ->zoom(6)
            ->addMarker(new Marker(
                position: new Point(45.7534031, 4.8295061),
                title: 'Lyon',
                infoWindow: new InfoWindow(
                    content: '<p>Thank you <a href="https://github.com/Kocal">@Kocal</a> for this component!</p>',
                ),
                icon: $icon
            ));

        $leafletOptions = (new LeafletOptions())
            ->tileLayer(new TileLayer(
                url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                options: [
                    'minZoom' => 5,
                    'maxZoom' => 10,
                ]
            ))
            ->attributionControl(false)
            ->attributionControlOptions(new AttributionControlOptions(ControlPosition::BOTTOM_LEFT))
            ->zoomControl(false)
            ->zoomControlOptions(new ZoomControlOptions(ControlPosition::TOP_LEFT));

// Add the custom options to the map
        $map->options($leafletOptions);

        return $this->render('map/index.html.twig', [
            'map' => $map,
        ]);
    }
}
