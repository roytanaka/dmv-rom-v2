// PROTOTYPE HARNESS (#405) — throwaway.
//
// Mounts the after-shift entry variants on their own, with the app's real stylesheet
// and real shadcn-vue components but none of its chrome. Each variant draws enough of
// its host surface's heading to be judged in place.
import { createApp, h } from 'vue';
import AfterShiftPrototype from '../resources/js/prototypes/aftershift/AfterShiftPrototype.vue';
import './harness.css';

createApp({
    render: () =>
        h('div', { class: 'mx-auto max-w-5xl p-4 sm:p-6' }, [
            h(
                'p',
                { class: 'text-muted-foreground border-input mb-4 border border-dashed px-3 py-2 text-xs' },
                'Prototype harness — no app chrome, no sidebar, no login. Arrow keys cycle variants; the bar at the bottom switches Group, viewer and clock.',
            ),
            h(AfterShiftPrototype),
        ]),
}).mount('#app');
