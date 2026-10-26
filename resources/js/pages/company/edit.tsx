import { Form, Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import VerificationController from '@/actions/App/Http/Controllers/VerificationController';
import Heading from '@/components/heading';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { VerifiedBadge } from '@/components/verified-badge';
import { formatBytes, formatDate } from '@/lib/format';
import { CompanyProfileFields } from '@/components/company-profile-fields';
import { edit } from '@/routes/company';
import { show as billingShow } from '@/routes/company/billing';
import { show as documentShow } from '@/routes/verifications/documents';
import type { CompanySummary, Verification } from '@/types';

type Props = {
    company: CompanySummary & {
        description: string | null;
        phone: string | null;
        email: string | null;
    };
    canSubmitVerification: boolean;
    maxDocuments: number;
    verifications: Verification[];
};

export default function EditCompany({
    company,
    canSubmitVerification,
    maxDocuments,
    verifications,
}: Props) {
    const latest = verifications[0];

    return (
        <>
            <Head title="My company" />
            <div className="mx-auto grid w-full max-w-5xl gap-8 p-4 lg:grid-cols-[1fr_22rem]">
                <section aria-labelledby="profile-heading">
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        {company.logo_url && (
                            <img
                                src={company.logo_url}
                                alt=""
                                className="size-12 rounded-md border object-cover"
                            />
                        )}
                        <div>
                            <h1
                                id="profile-heading"
                                className="flex flex-wrap items-center gap-2 text-xl font-semibold"
                            >
                                {company.name}
                                <VerifiedBadge verified={company.is_verified} />
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {company.type_label}
                            </p>
                        </div>
                        <Button variant="outline" asChild className="ml-auto">
                            <Link href={billingShow()}>Plan & billing</Link>
                        </Button>
                    </div>

                    <Form
                        {...CompanyController.update.form()}
                        encType="multipart/form-data"
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <CompanyProfileFields
                                    errors={errors}
                                    defaults={company}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save profile'}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <aside
                    className="space-y-6"
                    aria-labelledby="verification-heading"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle
                                id="verification-heading"
                                className="flex items-center gap-2"
                            >
                                Verification
                                {latest && (
                                    <StatusBadge status={latest.status} />
                                )}
                            </CardTitle>
                            <CardDescription>
                                {!latest &&
                                    'Send your registration details to get the Verified badge.'}
                                {latest?.status === 'pending' &&
                                    'Your application is waiting for review.'}
                                {latest?.status === 'approved' &&
                                    `Verified on ${formatDate(latest.reviewed_at)}.`}
                                {latest?.status === 'rejected' &&
                                    'Your last application was rejected. You can apply again.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {latest?.status === 'rejected' &&
                                latest.rejection_reason && (
                                    <p className="rounded-md border border-red-500/40 bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                                        {latest.rejection_reason}
                                    </p>
                                )}

                            {canSubmitVerification && (
                                <Form
                                    {...VerificationController.store.form()}
                                    encType="multipart/form-data"
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="registration_number">
                                                    Registration number
                                                </Label>
                                                <Input
                                                    id="registration_number"
                                                    name="registration_number"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.registration_number
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="documents">
                                                    Documents
                                                </Label>
                                                <Input
                                                    id="documents"
                                                    name="documents[]"
                                                    type="file"
                                                    multiple
                                                    required
                                                    accept="application/pdf,image/png,image/jpeg"
                                                    aria-describedby="documents-help"
                                                />
                                                <p
                                                    id="documents-help"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Up to {maxDocuments} files:
                                                    PDF, JPG or PNG, 5 MB each.
                                                    Only you and our reviewers
                                                    can see them.
                                                </p>
                                                <InputError
                                                    message={
                                                        errors.documents ??
                                                        errors['documents.0']
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="notes">
                                                    Notes for the reviewer
                                                </Label>
                                                <Textarea
                                                    id="notes"
                                                    name="notes"
                                                    rows={3}
                                                />
                                                <InputError
                                                    message={errors.notes}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                {processing
                                                    ? 'Sending…'
                                                    : 'Apply for verification'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>

                    {verifications.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Applications
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="space-y-4 text-sm">
                                    {verifications.map((verification) => (
                                        <li
                                            key={verification.id}
                                            className="space-y-1"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span>
                                                    {formatDate(
                                                        verification.created_at,
                                                    )}
                                                </span>
                                                <StatusBadge
                                                    status={verification.status}
                                                />
                                            </div>
                                            <ul className="space-y-1">
                                                {verification.documents.map(
                                                    (document) => (
                                                        <li
                                                            key={document.index}
                                                        >
                                                            <a
                                                                href={documentShow.url(
                                                                    {
                                                                        verification:
                                                                            verification.id,
                                                                        index: document.index,
                                                                    },
                                                                )}
                                                                className="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                <FileText
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                {document.name}
                                                            </a>
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                (
                                                                {formatBytes(
                                                                    document.size,
                                                                )}
                                                                )
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    )}
                </aside>
            </div>
        </>
    );
}

EditCompany.layout = {
    breadcrumbs: [{ title: 'My company', href: edit() }],
};
