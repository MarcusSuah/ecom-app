import { Form, Head } from '@inertiajs/react';
import { Megaphone, Send } from 'lucide-react';
import { useState } from 'react';
import { DataTableFooter } from '@/components/data-table-footer';
import { DetailDialog } from '@/components/detail-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import notificationRoutes from '@/routes/admin/notifications';
import type { Paginated } from '@/types';

type Announcement = {
    id: number;
    title: string;
    body: string;
    link_url: string | null;
    audience: string;
    recipients_count: number;
    sent_by: string | null;
    created_at: string | null;
};

type Props = {
    announcements: Paginated<Announcement>;
    audiences: { value: string; label: string; count: number }[];
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function NotificationsIndex({
    announcements,
    audiences,
}: Props) {
    const [viewing, setViewing] = useState<Announcement | null>(null);

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Notifications"
                    description="Send an announcement to members' notification bell, and optionally by email."
                />

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Megaphone
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            New announcement
                        </CardTitle>
                        <CardDescription>
                            Members of this marketplace only. Blocked accounts
                            are skipped.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...notificationRoutes.store.form()}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                            transform={(data) => ({
                                ...data,
                                by_email: data.by_email === 'on',
                            })}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="audience">
                                            Send to
                                        </Label>
                                        <select
                                            id="audience"
                                            name="audience"
                                            className={selectClass}
                                            defaultValue="everyone"
                                        >
                                            {audiences.map((audience) => (
                                                <option
                                                    key={audience.value}
                                                    value={audience.value}
                                                >
                                                    {audience.label} (
                                                    {audience.count})
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.audience} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Title</Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            maxLength={150}
                                            required
                                        />
                                        <InputError message={errors.title} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="body">Message</Label>
                                        <Textarea
                                            id="body"
                                            name="body"
                                            rows={4}
                                            maxLength={2000}
                                            required
                                        />
                                        <InputError message={errors.body} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="link_url">
                                            Link{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <Input
                                            id="link_url"
                                            name="link_url"
                                            placeholder="/products or https://…"
                                        />
                                        <InputError message={errors.link_url} />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox name="by_email" />
                                        Also send by email
                                    </label>
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <Send aria-hidden="true" />
                                            {processing
                                                ? 'Sending…'
                                                : 'Send announcement'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Sent</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Audience</TableHead>
                                        <TableHead className="text-right">
                                            Recipients
                                        </TableHead>
                                        <TableHead>Sent by</TableHead>
                                        <TableHead>Sent</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {announcements.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                Nothing sent yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        announcements.data.map(
                                            (announcement) => (
                                                <TableRow
                                                    key={announcement.id}
                                                    className="cursor-pointer"
                                                    onClick={() =>
                                                        setViewing(announcement)
                                                    }
                                                >
                                                    <TableCell className="font-medium">
                                                        {announcement.title}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="secondary">
                                                            {
                                                                announcement.audience
                                                            }
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {
                                                            announcement.recipients_count
                                                        }
                                                    </TableCell>
                                                    <TableCell>
                                                        {announcement.sent_by ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {formatDate(
                                                            announcement.created_at,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataTableFooter
                            page={announcements}
                            noun="announcement(s)"
                        />
                    </CardContent>
                </Card>
            </div>

            <DetailDialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
                title={viewing?.title}
                rows={
                    viewing
                        ? [
                              { label: 'Audience', value: viewing.audience },
                              {
                                  label: 'Recipients',
                                  value: viewing.recipients_count,
                              },
                              { label: 'Sent by', value: viewing.sent_by },
                              {
                                  label: 'Sent',
                                  value: formatDate(viewing.created_at),
                              },
                              { label: 'Link', value: viewing.link_url },
                              {
                                  label: 'Message',
                                  value: (
                                      <span className="whitespace-pre-line">
                                          {viewing.body}
                                      </span>
                                  ),
                              },
                          ]
                        : []
                }
            />
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notificationRoutes.index() }],
};
