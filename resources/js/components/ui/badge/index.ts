import { cva, type VariantProps } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

// ROM-tuned shadcn-vue Badge tone taxonomy. Status variants use the soft-tint
// pattern (`-bg` surface + solid token text). The deliberate divergence from
// upstream: `destructive` is SOFT-tinted here (bg-destructive-bg / text-destructive),
// not shadcn's solid red fill, so status chips read calm — the destructive
// *Button* keeps its solid fill on purpose. Corners are squared per the
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
                warning: 'bg-warning-bg text-warning',
                destructive: 'bg-destructive-bg text-destructive',
                outline: 'border border-input bg-background text-foreground',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

export type BadgeVariants = VariantProps<typeof badgeVariants>;
