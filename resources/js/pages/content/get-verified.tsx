import { Link, usePage } from '@inertiajs/react';
import { ContentPage, type ContentPageProps } from '@/components/content-page';
import { Button } from '@/components/ui/button';
import { VerifiedBadge } from '@/components/verified-badge';
import { register } from '@/routes';
import { edit as editCompany } from '@/routes/company';
import type { SharedData } from '@/types';

export default function GetVerified({ title, facts }: ContentPageProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <ContentPage
            title={title}
            current="get-verified"
            protectionLabel={facts.protectionLabel}
            lead="Verification tells buyers a real, registered business stands behind your listings."
        >
            <section>
                <h2>What buyers see</h2>
                <p>
                    Verified companies show{' '}
                    <VerifiedBadge verified className="align-middle" /> on their
                    profile, products and quotes. Buyers can filter to verified
                    suppliers only, and only verified suppliers receive requests
                    for quotes.
                </p>
            </section>

            <section>
                <h2>What to upload</h2>
                <ul>
                    <li>
                        Your business registration number (for example from
                        RDB).
                    </li>
                    <li>
                        Your registration certificate, plus any trading licence
                        or tax certificate that applies to you.
                    </li>
                    <li>
                        Up to {facts.maxDocuments} files, as PDF, JPG or PNG,
                        each up to 5 MB.
                    </li>
                </ul>
                <p>
                    Documents are only seen by the marketplace's verification
                    team.
                </p>
            </section>

            <section>
                <h2>How the review works</h2>
                <ol>
                    <li>Submit your documents from your company page.</li>
                    <li>
                        A verifier checks that the registration is current and
                        matches your company name.
                    </li>
                    <li>
                        You get a notification either way. If something is
                        missing, the reason is shown and you can resubmit.
                    </li>
                </ol>
                <p>Most reviews finish within two working days.</p>
            </section>

            <Button asChild>
                <Link href={auth.user ? editCompany() : register()}>
                    {auth.user ? 'Go to my company' : 'Create an account'}
                </Link>
            </Button>
        </ContentPage>
    );
}
