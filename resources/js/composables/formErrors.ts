/**
 * Inertia types form errors by top-level field, but Laravel returns nested keys such as
 * "terms.0.name". This reads those keys without casting at every call site.
 */
export function errorFor(errors: object, key: string): string | undefined {
    return (errors as Record<string, string | undefined>)[key];
}
