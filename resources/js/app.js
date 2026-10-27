import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import kiosk from './kiosk';
import display from './display';
import staffDashboard from './staff';
import remoteForm from './remote';

Alpine.plugin(focus);
Alpine.data('kiosk', kiosk);
Alpine.data('display', display);
Alpine.data('staffDashboard', staffDashboard);
Alpine.data('remoteForm', remoteForm);

window.Alpine = Alpine;
Alpine.start();
