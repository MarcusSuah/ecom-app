import { Form, Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import AdminVerificationController from '@/actions/App/Http/Controllers/Admin/VerificationController';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatBytes, formatDate } from '@/lib/format';
import verificationRoutes from '@/routes/admin/verifications';
import { show as documentShow } from '@/routes/verifications/documents';
import type { CompanySummary, Verification } from '@/types';

type Props = {
    verification: Verification;
    company: CompanySummary & {
        description: string | null;
        phone: string | null;
        email: string | null;
        owner: { name: string; email: string };
    };
    history: Verification[];
};

export default function VerificationShow({
    verification,
    company,
    history,
}: Props) {
    const [rejecting, setRejecting] = useState(false);
    const reviewForm = AdminVerificationController.review.form(verification.id);

    return (
        <>
            <Head title={`Verification · ${company.name}`} />
            <div className="grid gap-6 p-4 lg:grid-cols-[1fr_24rem]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center gap-2">
                                {company.name}
                                <StatusBadge status={verification.status} />
                            </CardTitle>
                            <CardDescription>
                                {company.type_label} · {company.city ?? '—'},{' '}
                                {company.country}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Registration number
                                    </dt>
                                    <dd className="font-mono">
                                        {verification.registration_number}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Submitted
                                    </dt>
                                    <dd>
                                        {formatDate(verification.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Owner
                                    </dt>
                                    <dd>
                                        {company.owner.name} (
                                        {company.owner.email})
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Company contact
                                    </dt>
                                    <dd>
                                        {[company.phone, company.email]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </dd>
                                </div>
                                {verification.notes && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-muted-foreground">
                                            Notes from the applicant
                                        </dt>
                                        <dd className="whitespace-pre-line">
                                            {verification.notes}
                                        </dd>
                                    </div>
                                )}
                                {company.description && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-muted-foreground">
                                            About
                                        </dt>
                                        <dd className="whitespace-pre-line">
                                            {company.description}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Documents
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {verification.documents.map((document) => (
                                    <li key={document.index}>
                                        <a
                                            href={documentShow.url({
                                                verification: verification.id,
                                                index: document.index,
                                            })}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-2 text-primary underline-offset-4 hover:underline"
                                        >
                                            <FileText
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {document.name}
                                        </a>
                                        <span className="text-sm text-muted-foreground">
                                            {' '}
                                            ({formatBytes(document.size)})
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    {history.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Earlier applications
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3 text-sm">
                                    {history.map((previous) => (
                                        <li key={previous.id}>
                                            <div className="flex items-center gap-2">
                                                {formatDate(
                                                    previous.created_at,
                                                )}{' '}
                                                <StatusBadge
                                                    status={previous.status}
                                                />
                                            </div>
                                            {previous.rejection_reason && (
                                                <p className="text-muted-foreground">
                                                    {previous.rejection_reason}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <aside>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Decision
                            </CardTitle>
                            {verification.status !== 'pending' && (
                                <CardDescription>
                                    {verification.status === 'approved'
                                        ? 'Approved'
                                        : 'Rejected'}{' '}
                                    by {verification.reviewer?.name ?? '—'} on{' '}
                                    {formatDate(verification.reviewed_at)}.
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent>
                            {verification.status === 'rejected' &&
                                verification.rejection_reason && (
                                    <p className="text-sm">
                                        {verification.rejection_reason}
                                    </p>
                                )}
                            {verification.status === 'pending' && (
                                <Form {...reviewForm} className="space-y-4">
                                    {({ processing, errors }) => (
                                        <>
                                            {rejecting && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="rejection_reason">
                                                        Reason (sent to the
                                                        company)
                                                    </Label>
                                                    <Textarea
                                                        id="rejection_reason"
                                                        name="rejection_reason"
                                                        rows={4}
                                                        required
                                                        autoFocus
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.rejection_reason
                                                        }
                                                    />
                                                </div>
                                            )}
                                            <InputError
                                                message={errors.decision}
                                            />
                                            <div className="flex flex-wrap gap-2">
                                                {rejecting ? (
                                                    <>
                                                        <Button
                                                            type="submit"
                                                            name="decision"
                                                            value="rejected"
                                                            variant="destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Reject application
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                setRejecting(
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            Back
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <>
                                                        <Button
                                                            type="submit"
                                                            name="decision"
                                                            value="approved"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setRejecting(
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            Reject…
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </>
    );
}

VerificationShow.layout = {
    breadcrumbs: [{ title: 'Verifications', href: verificationRoutes.index() }],
};
