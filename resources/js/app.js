import Alpine from 'alpinejs';
import { startScanner } from './devices';
import { customerDisplay } from './display';
import { posApp, stockApp } from './shop';

window.Alpine = Alpine;

Alpine.data('posApp', posApp);
Alpine.data('stockApp', stockApp);
Alpine.data('customerDisplay', customerDisplay);

startScanner();
Alpine.start();
