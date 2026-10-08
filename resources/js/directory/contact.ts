// A phone number as a `tel:` link (#750), so a tap opens the dialler. The stored number
// is free text ("(416) 586-8000"); the link keeps its digits and a leading plus.
export const telHref = (phone: string) => `tel:${phone.replace(/(?!^\+)[^\d]/g, '')}`;
