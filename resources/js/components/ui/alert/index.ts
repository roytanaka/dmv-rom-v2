import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';

export { default as Alert } from './Alert.vue';
export { default as AlertDescription } from './AlertDescription.vue';
export { default as AlertTitle } from './AlertTitle.vue';

// ROM-tuned shadcn-vue Alert (#848). Square corners per square-by-default; the leading icon is a
// flex sibling instead of upstream's absolutely-placed svg, so an action can sit at the end of the
// row. Tones follow the Badge taxonomy (docs/conventions.md § Styling): `info` is the status wash
// (`bg-info-bg`, icon in `text-info`), `destructive` the soft tint with its dark on-tint text.
export const alertVariants = cva('relative flex w-full gap-3 rounded-none border p-4 text-base [&>svg]:mt-0.5 [&>svg]:size-5 [&>svg]:shrink-0', {
    variants: {
        variant: {
            default: 'bg-background text-foreground [&>svg]:text-foreground',
            info: 'border-transparent bg-info-bg text-foreground [&>svg]:text-info',
            destructive: 'border-transparent bg-destructive-bg text-destructive-tint-foreground [&>svg]:text-destructive-tint-foreground',
        },
    },
    defaultVariants: {
        variant: 'default',
    },
});

export type AlertVariants = VariantProps<typeof alertVariants>;
