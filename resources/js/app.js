import Alpine from 'alpinejs';
import { salesChart } from './chart';
import { startScanner } from './devices';
import { customerDisplay } from './display';
import { startKeyboard } from './keyboard';
import { posApp, stockApp } from './shop';

window.Alpine = Alpine;

Alpine.data('posApp', posApp);
Alpine.data('stockApp', stockApp);
Alpine.data('customerDisplay', customerDisplay);
Alpine.data('salesChart', salesChart);

startScanner();
startKeyboard();
Alpine.start();
