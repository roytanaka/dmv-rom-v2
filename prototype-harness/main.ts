// PROTOTYPE HARNESS (#330) — throwaway.
//
// Mounts the Schedule variants on their own, with the app's real stylesheet and real
// shadcn-vue components but none of its chrome. The chrome version lives at
// /groups/{slug}/scheduling once Sail is up; this is the one-command version.
import { createApp, h } from 'vue';
import SchedulePrototype from '../resources/js/prototypes/schedule/SchedulePrototype.vue';
import './harness.css';

createApp({
    render: () =>
        h('div', { class: 'mx-auto max-w-6xl p-4 sm:p-6' }, [
            h(
                'p',
                { class: 'mb-4 border border-dashed border-input px-3 py-2 text-xs text-muted-foreground' },
                'Prototype harness — no app chrome, no sidebar, no login. Open /groups/reception/scheduling with Sail running to see these inside the real Group page.',
            ),
            h(SchedulePrototype),
        ]),
}).mount('#app');
