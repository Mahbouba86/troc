// assets/bootstrap.js

import { startStimulusApp } from '@symfony/stimulus-bundle';

// 1) Boot Stimulus (AssetMapper way)
export const app = startStimulusApp();

// 2) Auto-register all controllers in /assets/controllers
//    Matches Foo_controller.js / .ts
import.meta.glob('./controllers/**/*_controller.{js,ts}', { eager: true });

// 3) Leaflet (if you use UX Map / Leaflet directly)
import 'leaflet/dist/leaflet.css';


delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl:      'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl:    'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});
