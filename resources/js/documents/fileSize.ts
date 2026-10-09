/**
 * A Document's size in the page's language: kilobytes under a megabyte, megabytes under a
 * gigabyte, then gigabytes. Used by the library list and the image viewer's file card (#781).
 */
export function formatFileSize(bytes: number | null, locale: string): string {
    if (bytes === null) return '';
    const [unit, value] =
        bytes < 1024 ** 2
            ? (['kilobyte', bytes / 1024] as const)
            : bytes < 1024 ** 3
              ? (['megabyte', bytes / 1024 ** 2] as const)
              : (['gigabyte', bytes / 1024 ** 3] as const);

    return new Intl.NumberFormat(locale, { style: 'unit', unit, maximumFractionDigits: value < 10 ? 1 : 0 }).format(Math.max(value, 0.1));
}
