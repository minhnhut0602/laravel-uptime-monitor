<?php

namespace Spatie\UptimeMonitor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Spatie\UptimeMonitor\Exceptions\CannotSaveSite;
use Spatie\UptimeMonitor\Models\Enums\SslCertificateStatus;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Spatie\UptimeMonitor\Models\Presenters\SitePresenter;
use Spatie\UptimeMonitor\Models\Traits\SupportsSslCertificateCheck;
use Spatie\UptimeMonitor\Models\Traits\SupportsUptimeCheck;
use Spatie\Url\Url;

class Site extends Model
{
    use SupportsSslCertificateCheck,
        SupportsUptimeCheck,
        SitePresenter;

    protected $guarded = [];

    protected $dates = [
        'uptime_last_check_date',
        'uptime_status_last_change_date',
        'down_event_fired_on_date',
        'ssl_certificate_expiration_date',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'check_ssl_certificate' => 'boolean',
    ];


    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function getUrlAttribute()
    {
        return Url::fromString($this->attributes['url']);
    }

    public static function boot()
    {
        static::saving(function (Site $site) {
            if (static::alreadyExists($site)) {
                throw CannotSaveSite::alreadyExists($site);
            }

            if (is_null($site->uptime_status_last_change_date)) {
                $site->uptime_status_last_change_date = Carbon::now();

                return;
            }

            if ($site->getOriginal('uptime_status') != $site->uptime_status) {
                $site->uptime_status_last_change_date = Carbon::now();
            }
        });
    }

    public function isHealthy()
    {
        if (in_array($this->uptime_status, [UptimeStatus::DOWN, UptimeStatus::NOT_YET_CHECKED])) {
            return false;
        }

        if ($this->check_ssl_certificate && $this->ssl_certificate_status === SslCertificateStatus::INVALID) {
            return false;
        }

        return true;
    }

    protected static function alreadyExists(Site $site): bool
    {
        $query = static::where('url', $site->url);

        if ($site->exists) {
            $query->where('id', '<>', $site->id);
        }

        return (bool)$query->first();
    }
}
