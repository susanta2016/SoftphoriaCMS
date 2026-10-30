<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * newsletter_events.type. Delivery/Bounce/SoftBounce/Complaint/Reject/
 * DeliveryDelay/RenderingFailure only come from the provider (SES events);
 * Open/Click from SES or the module's own tracking links; Unsubscribe from
 * the unsubscribe page or the one-click header.
 */
enum NewsletterEventType: string implements HasLabel
{
    case Delivery = 'delivery';
    case Bounce = 'bounce';
    case SoftBounce = 'soft_bounce';
    case Complaint = 'complaint';
    case Open = 'open';
    case Click = 'click';
    case Unsubscribe = 'unsubscribe';
    case Reject = 'reject';
    case DeliveryDelay = 'delivery_delay';
    case RenderingFailure = 'rendering_failure';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delivery => 'Delivered',
            self::Bounce => 'Hard bounce',
            self::SoftBounce => 'Soft bounce',
            self::Complaint => 'Spam complaint',
            self::Open => 'Open',
            self::Click => 'Click',
            self::Unsubscribe => 'Unsubscribe',
            self::Reject => 'Rejected by provider',
            self::DeliveryDelay => 'Delivery delayed',
            self::RenderingFailure => 'Rendering failure',
        };
    }
}
