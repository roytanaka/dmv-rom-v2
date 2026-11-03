// The shape of an application (#928, ADR-0038 §8), shared by Add applicant, the Applicant record
// and the public form (#930). Field names match the Member record's.

export interface ApplicationFields {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    alternate_phone: string;
    business_phone: string;
    address_street: string;
    address_city: string;
    address_province: string;
    address_postal_code: string;
    address_country: string;
    membership_number: string;
    membership_expiry: string;
    membership_lifetime: boolean;
    emergency_contact: string;
    emergency_contact_number: string;
}

/** A blank application, or one filled from a stored Applicant (nulls become empty fields). */
export const applicationFields = (from: Partial<Record<keyof ApplicationFields, unknown>> = {}): ApplicationFields => ({
    first_name: String(from.first_name ?? ''),
    last_name: String(from.last_name ?? ''),
    email: String(from.email ?? ''),
    phone: String(from.phone ?? ''),
    alternate_phone: String(from.alternate_phone ?? ''),
    business_phone: String(from.business_phone ?? ''),
    address_street: String(from.address_street ?? ''),
    address_city: String(from.address_city ?? ''),
    address_province: String(from.address_province ?? ''),
    address_postal_code: String(from.address_postal_code ?? ''),
    address_country: String(from.address_country ?? ''),
    membership_number: String(from.membership_number ?? ''),
    membership_expiry: String(from.membership_expiry ?? ''),
    membership_lifetime: Boolean(from.membership_lifetime ?? false),
    emergency_contact: String(from.emergency_contact ?? ''),
    emergency_contact_number: String(from.emergency_contact_number ?? ''),
});
