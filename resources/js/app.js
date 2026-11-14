import Alpine from 'alpinejs';
import { startScanner } from './devices';
import { posApp, stockApp } from './shop';

window.Alpine = Alpine;

Alpine.data('posApp', posApp);
Alpine.data('stockApp', stockApp);

startScanner();
Alpine.start();
