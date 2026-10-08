// A phone number as a `tel:` link (#750), so a tap opens the dialler. The stored number
// is free text ("(416) 586-8000 ext. 12"); the link keeps the main number's digits and a
// leading plus, and drops an extension. Text with no digits gets no link (undefined).
export const telHref = (phone: string): string | undefined => {
    const main = phone.split(/x|ext|poste/i)[0];
    const digits = main.trim().replace(/(?!^\+)[^\d]/g, '');

    return /\d/.test(digits) ? `tel:${digits}` : undefined;
};
