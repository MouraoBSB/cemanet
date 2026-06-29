<?php

namespace Tests\Unit\Palestras;

use App\Support\Palestras\LinkDrive;
use PHPUnit\Framework\TestCase;

class LinkDriveTest extends TestCase
{
    public function test_file_d_vira_download(): void
    {
        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            LinkDrive::paraDownload('https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing')
        );
    }

    public function test_open_id_vira_download(): void
    {
        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            LinkDrive::paraDownload('https://drive.google.com/open?id=1ABCdefg_hij')
        );
    }

    public function test_idempotente_com_amp_encodado(): void
    {
        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            LinkDrive::paraDownload('https://drive.google.com/uc?export=download&amp;id=1ABCdefg_hij')
        );
    }

    public function test_pasta_do_drive_fica_intacta(): void
    {
        $url = 'https://drive.google.com/drive/folders/1ABCdefg_hijKLMNOpqrs';
        $this->assertSame($url, LinkDrive::paraDownload($url));
    }

    public function test_nao_drive_com_token_longo_fica_intacto(): void
    {
        $url = 'https://www.dropbox.com/s/AAAAAAAAAAAAAAAAAAAAAAAAAA/x.pptx';
        $this->assertSame($url, LinkDrive::paraDownload($url));
    }

    public function test_nao_drive_simples_fica_intacto(): void
    {
        $this->assertSame('https://exemplo.com/arquivo.pptx', LinkDrive::paraDownload('https://exemplo.com/arquivo.pptx'));
    }

    public function test_vazio_e_nulo_viram_nulo(): void
    {
        $this->assertNull(LinkDrive::paraDownload(null));
        $this->assertNull(LinkDrive::paraDownload('   '));
    }
}
