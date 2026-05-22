<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 28px; border-bottom: 2px solid {{ config('brand.colors.red') }}; padding-bottom: 20px;">
    <tr>
        <td style="vertical-align: middle;">
            <img
                src="{{ $logoUrl ?? url(config('brand.logo')) }}"
                alt="{{ config('brand.name') }}"
                height="52"
                style="display: block; height: 52px; width: auto;"
            />
        </td>
        <td style="vertical-align: middle; text-align: right; font-family: Arial, sans-serif;">
            <p style="margin: 0; font-size: 11px; color: {{ config('brand.colors.muted') }}; letter-spacing: 0.04em; text-transform: uppercase;">
                {{ $slot ?? config('brand.tagline') }}
            </p>
        </td>
    </tr>
</table>
