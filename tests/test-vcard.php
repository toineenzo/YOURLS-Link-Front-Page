<?php
/**
 * vCard generator coverage. The plugin serves /contact.vcf?type=personal|business
 * for visitor downloads, so the output has to remain RFC 6350-compatible
 * across refactors.
 */

declare(strict_types=1);

class VCardTest extends PHPUnit\Framework\TestCase
{
    public function test_empty_contact_returns_null(): void
    {
        $this->assertNull(lfp_build_vcard([]));
        $this->assertNull(lfp_build_vcard([
            'name' => '', 'phone' => '', 'email' => '', 'website' => '', 'address' => '',
        ]));
    }

    public function test_personal_card_emits_required_lines(): void
    {
        $vcard = lfp_build_vcard([
            'name'    => 'Toine Rademacher',
            'phone'   => '+31 6 12 34 56 78',
            'email'   => 'hello@example.com',
            'website' => 'https://example.com',
        ]);

        $this->assertNotNull($vcard);
        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\n", $vcard);
        $this->assertStringEndsWith("END:VCARD\r\n", $vcard);
        $this->assertStringContainsString("FN:Toine Rademacher",       $vcard);
        $this->assertStringContainsString("EMAIL;TYPE=HOME,INTERNET:", $vcard);
        $this->assertStringContainsString("TEL;TYPE=CELL,VOICE:",      $vcard);
        $this->assertStringContainsString("URL:https://example.com",   $vcard);
    }

    public function test_business_card_includes_org_and_work_types(): void
    {
        $vcard = lfp_build_vcard([
            'name'  => 'Acme Corp',
            'phone' => '+31 20 555 0100',
            'email' => 'sales@example.com',
        ], 'Acme Corp');

        $this->assertNotNull($vcard);
        $this->assertStringContainsString("ORG:Acme Corp",             $vcard);
        $this->assertStringContainsString("EMAIL;TYPE=WORK,INTERNET:", $vcard);
        $this->assertStringContainsString("TEL;TYPE=WORK,VOICE:",      $vcard);
    }

    public function test_address_newlines_are_escaped(): void
    {
        $vcard = lfp_build_vcard([
            'name'    => 'X',
            'address' => "Street 1\nCity, Country",
        ]);

        $this->assertNotNull($vcard);
        // RFC 6350 escapes \n inside line values; raw line breaks would
        // corrupt parsing on the receiving side.
        $this->assertStringContainsString('Street 1\\nCity', $vcard);
        $this->assertStringNotContainsString("Street 1\nCity", $vcard);
    }

    public function test_multi_word_name_splits_into_n_field(): void
    {
        $vcard = lfp_build_vcard(['name' => 'Toine Rademacher']);
        $this->assertNotNull($vcard);
        // Family name = last token, given name = the rest.
        $this->assertStringContainsString("N:Rademacher;Toine;;;", $vcard);
    }

    public function test_single_word_name_leaves_family_blank(): void
    {
        $vcard = lfp_build_vcard(['name' => 'Toine']);
        $this->assertNotNull($vcard);
        $this->assertStringContainsString("N:;Toine;;;", $vcard);
    }

    public function test_special_chars_are_rfc_escaped(): void
    {
        $vcard = lfp_build_vcard(['name' => 'A; B, C']);
        $this->assertNotNull($vcard);
        // Commas and semicolons inside values are literal-escaped.
        $this->assertStringContainsString('FN:A\\; B\\, C', $vcard);
    }
}
