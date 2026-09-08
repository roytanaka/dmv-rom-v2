import { cva, type VariantProps } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

// ROM-tuned shadcn-vue Badge tone taxonomy. Status variants use the soft-tint
// pattern (`-bg` surface + solid token text — except warning and destructive,
// whose solid hues are too light on their own tint to clear AA, so each uses a
// dark on-tint -foreground; see those variants). The divergence from upstream:
// `destructive` is SOFT-tinted here (bg-destructive-bg + dark red text), not
// shadcn's solid red fill, so status chips read calm — the destructive *Button*
// keeps its solid fill on purpose. Corners are squared per the
// square-by-default convention; the optional leading `dot` (Badge.vue) is the
// documented true-circle exception. See docs/conventions.md § Styling and the
// design system. `info` references the rom-slate utilities, which resolve to the
// identical value as the `info` status tokens (--info → --rom-slate).
export const badgeVariants = cva(
    'inline-flex items-center gap-1.5 rounded-none px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground',
                secondary: 'bg-secondary text-secondary-foreground',
                info: 'bg-rom-slate-50 text-rom-slate',
                success: 'bg-success-bg text-success',
                // warning text uses the dark -foreground, not the solid hue:
                // amber (#f08c00) on the soft tint is only 2.26:1 (fails AA),
                // while --warning-foreground (#422700) is ~13:1. Amber is the one
                // status hue too light to serve as its own on-tint text.
                warning: 'bg-warning-bg text-warning-foreground',
                // destructive text uses the dark on-tint -foreground, not the
                // solid hue: #d4322b on the soft tint is only 4.21:1 (fails AA),
                // while --destructive-tint-foreground (#b42318) is ~5.6:1.
                destructive: 'bg-destructive-bg text-destructive-tint-foreground',
                outline: 'border border-input bg-background text-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export type BadgeVariants = VariantProps<typeof badgeVariants>;
