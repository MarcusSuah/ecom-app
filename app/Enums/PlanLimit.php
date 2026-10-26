<?php

namespace App\Enums;

/**
 * What a plan allows. Numeric limits use null for "unlimited"; switches are
 * booleans. Stored in plans.limits keyed by the case value.
 */
enum PlanLimit: string
{
    case MAX_COMPANIES = 'max_companies';
    case CUSTOM_DOMAIN = 'custom_domain';
    case MAX_PRODUCTS = 'max_products';
    case MONTHLY_QUOTES = 'monthly_quotes';
    case COMMISSION_PERCENT = 'commission_percent';
    case FEATURED_LISTING = 'featured_listing';

    public function audience(): PlanAudience
    {
        return match ($this) {
            self::MAX_COMPANIES, self::CUSTOM_DOMAIN => PlanAudience::MARKETPLACE,
            default => PlanAudience::SELLER,
        };
    }

    public function isSwitch(): bool
    {
        return in_array($this, [self::CUSTOM_DOMAIN, self::FEATURED_LISTING], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::MAX_COMPANIES => 'Seller companies',
            self::CUSTOM_DOMAIN => 'Custom domain',
            self::MAX_PRODUCTS => 'Listed products',
            self::MONTHLY_QUOTES => 'Quotes per month',
            self::COMMISSION_PERCENT => 'Commission (%)',
            self::FEATURED_LISTING => 'Featured listing',
        };
    }

    /**
     * The value used when a plan does not set this limit: switches are off
     * and numeric limits are unlimited.
     */
    public function defaultValue(): ?bool
    {
        return $this->isSwitch() ? false : null;
    }
}
