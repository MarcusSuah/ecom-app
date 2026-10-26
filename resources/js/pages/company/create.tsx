import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { CompanyProfileFields } from '@/components/company-profile-fields';
import { cn } from '@/lib/utils';
import { create } from '@/routes/company';

type CompanyTypeOption = { value: 'supplier' | 'shop'; label: string };

const typeHelp: Record<CompanyTypeOption['value'], string> = {
    supplier:
        'Sell in bulk to businesses with minimum order quantities, tiered prices and quotes.',
    shop: 'Sell single items to nearby customers for pickup or delivery.',
};

export default function CreateCompany({
    types,
}: {
    types: CompanyTypeOption[];
}) {
    const [type, setType] = useState<CompanyTypeOption['value']>('supplier');

    return (
        <>
            <Head title="Start selling" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <Heading
                    title="Start selling"
                    description="Create your company profile. You can have one company on this marketplace."
                />

                <Form
                    {...CompanyController.store.form()}
                    className="space-y-6"
                    encType="multipart/form-data"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="space-y-3">
                                <legend className="text-sm font-medium">
                                    What kind of seller are you?
                                </legend>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {types.map((option) => (
                                        <label
                                            key={option.value}
                                            className={cn(
                                                'cursor-pointer rounded-lg border p-4 focus-within:ring-2 focus-within:ring-ring',
                                                type === option.value &&
                                                    'border-primary bg-primary/5',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="type"
                                                value={option.value}
                                                checked={type === option.value}
                                                onChange={() =>
                                                    setType(option.value)
                                                }
                                                className="sr-only"
                                            />
                                            <span className="block font-medium">
                                                {option.label}
                                            </span>
                                            <span className="mt-1 block text-sm text-muted-foreground">
                                                {typeHelp[option.value]}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.type} />
                            </fieldset>

                            <CompanyProfileFields errors={errors} />

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Creating…' : 'Create company'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateCompany.layout = {
    breadcrumbs: [{ title: 'Start selling', href: create() }],
};
