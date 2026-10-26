import { Plus, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type EditableTier = { min_qty: number | string; unit_price: string };

/**
 * Rows of "from quantity / unit price". Errors use the server's keys
 * (tiers.0.min_qty …) so each message appears under the row it belongs to.
 */
export function TierEditor({
    tiers,
    onChange,
    errors,
    currency,
    maxTiers,
    idPrefix = 'tier',
}: {
    tiers: EditableTier[];
    onChange: (tiers: EditableTier[]) => void;
    errors: Record<string, string | undefined>;
    currency: string;
    maxTiers: number;
    idPrefix?: string;
}) {
    const update = (index: number, key: keyof EditableTier, value: string) =>
        onChange(
            tiers.map((tier, i) =>
                i === index ? { ...tier, [key]: value } : tier,
            ),
        );

    return (
        <div className="space-y-3">
            <InputError message={errors.tiers} />
            <ol className="space-y-3">
                {tiers.map((tier, index) => {
                    const qtyError = errors[`tiers.${index}.min_qty`];
                    const priceError = errors[`tiers.${index}.unit_price`];

                    return (
                        <li
                            key={index}
                            className="grid grid-cols-[1fr_1fr_auto] items-start gap-3 rounded-md border p-3"
                        >
                            <div className="grid gap-1">
                                <Label htmlFor={`${idPrefix}-${index}-qty`}>
                                    From quantity
                                </Label>
                                <Input
                                    id={`${idPrefix}-${index}-qty`}
                                    type="number"
                                    min={1}
                                    value={tier.min_qty}
                                    onChange={(event) =>
                                        update(
                                            index,
                                            'min_qty',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(qtyError)}
                                    aria-describedby={`${idPrefix}-${index}-qty-error`}
                                />
                                <InputError
                                    id={`${idPrefix}-${index}-qty-error`}
                                    message={qtyError}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor={`${idPrefix}-${index}-price`}>
                                    Unit price ({currency})
                                </Label>
                                <Input
                                    id={`${idPrefix}-${index}-price`}
                                    inputMode="decimal"
                                    value={tier.unit_price}
                                    onChange={(event) =>
                                        update(
                                            index,
                                            'unit_price',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(priceError)}
                                    aria-describedby={`${idPrefix}-${index}-price-error`}
                                />
                                <InputError
                                    id={`${idPrefix}-${index}-price-error`}
                                    message={priceError}
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="mt-6"
                                disabled={tiers.length === 1}
                                onClick={() =>
                                    onChange(
                                        tiers.filter((_, i) => i !== index),
                                    )
                                }
                                aria-label={`Remove tier ${index + 1}`}
                            >
                                <X />
                            </Button>
                        </li>
                    );
                })}
            </ol>
            <Button
                type="button"
                variant="outline"
                onClick={() =>
                    onChange([...tiers, { min_qty: '', unit_price: '' }])
                }
                disabled={tiers.length >= maxTiers}
            >
                <Plus aria-hidden="true" />
                Add tier
            </Button>
        </div>
    );
}
