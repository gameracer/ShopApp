import Alpine from 'alpinejs';
import { posApp, stockApp } from './shop';

window.Alpine = Alpine;

Alpine.data('posApp', posApp);
Alpine.data('stockApp', stockApp);

Alpine.start();
