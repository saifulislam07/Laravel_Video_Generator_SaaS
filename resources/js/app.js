import Sort from '@alpinejs/sort';

// Livewire boots its own Alpine instance; register plugins on the `alpine:init`
// event so we don't pull in a second copy of Alpine.
document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(Sort);
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
