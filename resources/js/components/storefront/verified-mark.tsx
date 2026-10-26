/**
 * The template's rosette check for verified companies, with a text label for
 * screen readers (or shown when `label` is set).
 */
export function VerifiedMark({ label }: { label?: string }) {
    return (
        <span className="sf-ver">
            <svg viewBox="0 0 16 16" aria-hidden="true" className="size-3.5">
                <path
                    d="M8 1l2 1.4 2.4-.2.9 2.3 2 1.4-.8 2.3.8 2.3-2 1.4-.9 2.3-2.4-.2L8 15l-2-1.4-2.4.2-.9-2.3-2-1.4.8-2.3L.7 5.5l2-1.4.9-2.3 2.4.2z"
                    fill="currentColor"
                />
                <path
                    d="M5 8.2l2 2 4-4.2"
                    stroke="var(--sf-surface)"
                    strokeWidth="1.6"
                    fill="none"
                />
            </svg>
            {label ?? <span className="sr-only">Verified</span>}
        </span>
    );
}
