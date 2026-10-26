import { Head, usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

/**
 * Description, canonical and link-preview tags from the page's "meta" prop.
 * head-key matches the data-inertia keys in app.blade.php, so client-side
 * visits replace the tags the server printed.
 */
export function Seo() {
    const { meta, name } = usePage<SharedData>().props;

    if (!meta) {
        return null;
    }

    const title = meta.title ?? name;

    return (
        <Head>
            <meta
                head-key="description"
                name="description"
                content={meta.description}
            />
            <link head-key="canonical" rel="canonical" href={meta.url} />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={name}
            />
            <meta head-key="og:title" property="og:title" content={title} />
            <meta
                head-key="og:description"
                property="og:description"
                content={meta.description}
            />
            <meta head-key="og:type" property="og:type" content={meta.type} />
            <meta head-key="og:url" property="og:url" content={meta.url} />
            {meta.image && (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={meta.image}
                />
            )}
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={meta.image ? 'summary_large_image' : 'summary'}
            />
        </Head>
    );
}
