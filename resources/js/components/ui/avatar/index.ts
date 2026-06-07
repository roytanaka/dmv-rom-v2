import { cva, type VariantProps } from 'class-variance-authority';

export { default as Avatar } from './Avatar.vue';
export { default as AvatarFallback } from './AvatarFallback.vue';
export { default as AvatarImage } from './AvatarImage.vue';

export const avatarVariant = cva(
    // An avatar is always a circle — it depicts a person, the documented exception to
    // the square-by-default identity (ADR-0013, ADR-0014). The shape is baked in, not
    // a variant, so it cannot drift to a square/rounded-rect.
    'inline-flex items-center justify-center rounded-full font-normal text-foreground select-none shrink-0 bg-secondary overflow-hidden',
    {
        variants: {
            size: {
                sm: 'h-10 w-10 text-xs',
                base: 'h-16 w-16 text-2xl',
                lg: 'h-32 w-32 text-5xl',
            },
        },
    },
);

export type AvatarVariants = VariantProps<typeof avatarVariant>;
