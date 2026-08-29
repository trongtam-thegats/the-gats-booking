{{--
    Thu xac nhan dat ban.

    Viet bang bang va thuoc tinh style dat thang tren the: Outlook va Gmail
    cat bo phan lon CSS trong <style>, nen bo cuc phai dua vao <table>.

    Nhieu trinh doc thu CHAN ANH mac dinh. Vi vay moi thong tin quan trong
    deu co mat o dang chu ben duoi tam ve — anh chi la phan nhin cho de chiu,
    khong phai noi duy nhat chua noi dung.
--}}
@php
    $nen = $brand?->ground() ?: '#0e0d0c';
    $nhan = $brand?->accent_color ?: '#c8a15a';
    $chu = '#f4efe6';
    $mo = '#9c968c';
    $vien = '#2b2724';
    $t = fn (string $key, array $r = []) => __('booking.ticket.'.$key, $r, $locale);
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:{{ $nen }}; margin:0; padding:0; width:100%;">
    <tr>
        <td align="center" style="padding:28px 16px 40px;">

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:520px; width:100%;">

                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6;
                               color:{{ $chu }}; padding:0 4px 24px;">
                        {{ __('booking.notify.lead.'.$event, [
                            'name' => $booking->customer_name,
                            'code' => $booking->code,
                        ], $locale) }}
                    </td>
                </tr>

                @if ($ticketPng)
                    <tr>
                        <td align="center" style="padding:0 0 24px;">
                            {{-- Rong 540px, hien 340px de net tren man hinh mat do cao.
                                 embedData nhung thang anh vao thu duoi dang CID. --}}
                            <img src="{{ $message->embedData($ticketPng, 'ma-dat-ban-'.$booking->code.'.png', 'image/png') }}"
                                 width="340" alt="{{ $t('saved_title') }} {{ $booking->code }}"
                                 style="display:block; width:100%; max-width:340px; height:auto; border:0; outline:none;">
                        </td>
                    </tr>
                @endif

                {{-- Ban chu: van doc duoc khi trinh doc thu chan anh. --}}
                <tr>
                    <td style="background:#00000033; border:1px solid {{ $vien }}; border-radius:6px; padding:20px 22px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="font-family:Helvetica,Arial,sans-serif;">
                            <tr>
                                <td style="font-size:11px; letter-spacing:2px; text-transform:uppercase;
                                           color:{{ $mo }}; padding:0 0 4px;">{{ $t('code') }}</td>
                            </tr>
                            <tr>
                                <td style="font-size:22px; letter-spacing:4px; color:{{ $nhan }};
                                           padding:0 0 4px;">{{ $booking->code }}</td>
                            </tr>
                            <tr>
                                <td style="font-size:13px; color:{{ $mo }};
                                           padding:0 0 16px;">{{ $booking->statusLabel($locale) }}</td>
                            </tr>
                            @foreach ($rows as [$nhanHang, $giaTri])
                                <tr>
                                    <td style="font-size:11px; letter-spacing:1.5px; text-transform:uppercase;
                                               color:{{ $mo }}; padding:10px 0 2px;">{{ $nhanHang }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:16px; line-height:1.45; color:{{ $chu }};">{{ $giaTri }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                @if ($booking->status === \App\Models\Booking::STATUS_PENDING)
                    {{-- Don chua duyet: hen ro voi khach la quan se goi lai. --}}
                    <tr>
                        <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; line-height:1.6;
                                   color:{{ $mo }}; padding:16px 6px 0;">
                            {{ __('booking.ticket.pending_note', ['venue' => $booking->branch->name], $locale) }}
                        </td>
                    </tr>
                @endif

                <tr>
                    <td align="center" style="padding:24px 0 0;">
                        <a href="{{ $ticketUrl }}"
                           style="display:inline-block; font-family:Helvetica,Arial,sans-serif; font-size:15px;
                                  font-weight:bold; color:{{ $nen }}; background:{{ $nhan }}; text-decoration:none;
                                  padding:14px 30px; border-radius:5px;">{{ __('booking.notify.link_hint', [], $locale) }}</a>
                    </td>
                </tr>

                @if ($booking->branch->phone)
                    <tr>
                        <td align="center" style="font-family:Helvetica,Arial,sans-serif; font-size:13px;
                                                  line-height:1.6; color:{{ $mo }}; padding:22px 8px 0;">
                            {{ __('booking.notify.call_hint', ['phone' => $booking->branch->phone], $locale) }}
                        </td>
                    </tr>
                @endif

                <tr>
                    <td align="center" style="font-family:Helvetica,Arial,sans-serif; font-size:12px;
                                              color:{{ $mo }}; padding:26px 8px 0;">
                        {{ $booking->branch->name }}@if ($booking->branch->address) · {{ $booking->branch->address }}@endif
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>
