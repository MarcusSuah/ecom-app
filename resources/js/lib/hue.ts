/**
 * A stable hue (0–359) for a category, shop or supplier, so each one keeps
 * its own colour everywhere on the storefront. Same key, same hue.
 */
export function hueFor(key: string): number {
    let hash = 5381;

    for (let index = 0; index < key.length; index++) {
        hash = (hash * 33) ^ key.charCodeAt(index);
    }

    return Math.abs(hash) % 360;
}

/** Style object setting the --h custom property used by .sf-thumb, .sf-ico and .sf-avatar. */
export function hueStyle(key: string): React.CSSProperties {
    return { '--h': String(hueFor(key)) } as React.CSSProperties;
}

/** Two-letter mark for a name, e.g. "Coffee, tea and grains" → "CT". */
export function initials(name: string): string {
    return (
        name
            .replace(/[^\p{L}\p{N}\s]/gu, ' ')
            .split(/\s+/)
            .filter(
                (word) =>
                    word.length > 0 &&
                    !['and', 'of', 'the', '&'].includes(word.toLowerCase()),
            )
            .slice(0, 2)
            .map((word) => word[0])
            .join('')
            .toUpperCase() || '··'
    );
}
