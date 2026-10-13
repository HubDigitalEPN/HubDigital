import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './hubdigital-firmador';
import './pwa-notifications';
import './connectivity-status';
import Chart from 'chart.js/auto';
import './portal-dashboard';
import './portal-taxonomy-explorer';
import './portal-photos';
import './portal-charts';
import './portal-search';
import './portal-pwa';
import './portal-theme';
import './portal-phylum';

// Disponible para los paneles Livewire sin depender de scripts externos.
window.HubDigitalChart = Chart;

// Los componentes propios y Flux ya están registrados cuando Alpine recorre el DOM.
Livewire.start();
