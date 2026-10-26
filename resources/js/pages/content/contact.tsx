import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as storeMessage } from '@/routes/contact';
import { show as contentPage } from '@/routes/pages';
import type { SharedData } from '@/types';

type Props = {
    topics: { value: string; label: string }[];
    defaults: { name: string; email: string; topic: string };
    supportEmail: string | null;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none aria-invalid:border-destructive';

export default function Contact({ topics, defaults, supportEmail }: Props) {
    const { paymentProtectionLabel } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Contact us" />
            <div className="grid gap-10 lg:grid-cols-[1fr_18rem]">
                <section
                    aria-labelledby="contact-heading"
                    className="max-w-2xl space-y-6"
                >
                    <header className="space-y-2">
                        <h1
                            id="contact-heading"
                            className="text-3xl font-semibold tracking-tight"
                        >
                            Contact us
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Questions about buying, selling or a payment? We
                            usually reply within one working day.
                        </p>
                    </header>

                    <Form
                        {...storeMessage.form()}
                        resetOnSuccess={['message', 'phone']}
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Your name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={defaults.name}
                                            autoComplete="name"
                                            required
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            defaultValue={defaults.email}
                                            autoComplete="email"
                                            required
                                            aria-invalid={Boolean(errors.email)}
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">
                                            Phone{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            autoComplete="tel"
                                            aria-invalid={Boolean(errors.phone)}
                                        />
                                        <InputError message={errors.phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="topic">About</Label>
                                        <select
                                            id="topic"
                                            name="topic"
                                            defaultValue={defaults.topic}
                                            className={selectClass}
                                            required
                                            aria-invalid={Boolean(errors.topic)}
                                        >
                                            <option value="">Choose…</option>
                                            {topics.map((topic) => (
                                                <option
                                                    key={topic.value}
                                                    value={topic.value}
                                                >
                                                    {topic.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.topic} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="message">Message</Label>
                                    <Textarea
                                        id="message"
                                        name="message"
                                        rows={6}
                                        required
                                        minLength={10}
                                        maxLength={5000}
                                        aria-invalid={Boolean(errors.message)}
                                        placeholder="Include an order number if your message is about an order."
                                    />
                                    <InputError message={errors.message} />
                                </div>
                                {/* Honeypot: hidden from people and screen readers; bots fill it in. */}
                                <div
                                    aria-hidden="true"
                                    className="absolute -left-[9999px] h-px w-px overflow-hidden"
                                >
                                    <label htmlFor="website">
                                        Leave this empty
                                    </label>
                                    <input
                                        id="website"
                                        name="website"
                                        type="text"
                                        tabIndex={-1}
                                        autoComplete="off"
                                    />
                                </div>
                                <InputError message={errors.website} />
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Sending…' : 'Send message'}
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <aside
                    aria-label="Other ways to get help"
                    className="space-y-4 text-sm lg:border-l lg:pl-6"
                >
                    {supportEmail && (
                        <p className="flex items-center gap-2">
                            <Mail
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <a
                                href={`mailto:${supportEmail}`}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                {supportEmail}
                            </a>
                        </p>
                    )}
                    <div className="space-y-1.5">
                        <h2 className="font-semibold">Quick answers</h2>
                        <ul className="space-y-1.5">
                            <li>
                                <Link
                                    href={contentPage('payment-protection')}
                                    className="text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    {paymentProtectionLabel} and refunds
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={contentPage('shipping-and-customs')}
                                    className="text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    Delivery fees and customs
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={contentPage('seller-guide')}
                                    className="text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    Seller guide
                                </Link>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </>
    );
}
