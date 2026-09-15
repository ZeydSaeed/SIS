<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin Inertia shells for blueprint modules that have schema/API but no full UI yet.
 * Presentation only — no Domain/Application logic.
 */
final class OpsModulePageController extends Controller
{
    public function guardians(): Response
    {
        return $this->shell(
            'guardians',
            'أولياء الأمور',
            'ربط أولياء الأمور بالطلاب وفق مخطط guardians.',
        );
    }

    public function admission(): Response
    {
        return $this->shell(
            'admission',
            'القبول',
            'فترات التقديم وطلبات القبول — مخطط admission.',
        );
    }

    public function curriculum(): Response
    {
        return $this->shell(
            'curriculum',
            'المنهج',
            'البرامج والمواد والخطط الدراسية — مخطط curriculum.',
        );
    }

    public function promotion(): Response
    {
        return $this->shell(
            'promotion',
            'الترقية',
            'قواعد وسجلات الترقية — مخطط promotion (API جاهز).',
        );
    }

    public function transfers(): Response
    {
        return $this->shell(
            'transfers',
            'النقل',
            'طلبات وسجلات النقل بين المدارس — مخطط transfers (API جاهز).',
        );
    }

    public function graduation(): Response
    {
        return $this->shell(
            'graduation',
            'التخرج',
            'أهلية التخرج والاعتماد — مخطط graduation.',
        );
    }

    public function certificates(): Response
    {
        return $this->shell(
            'certificates',
            'الشهادات',
            'قوالب وإصدار الشهادات — مخطط certificates.',
        );
    }

    public function finance(): Response
    {
        return $this->shell(
            'finance',
            'المالية',
            'أنواع الرسوم والمدفوعات — مخطط finance (API جاهز).',
        );
    }

    public function holidays(): Response
    {
        return $this->shell(
            'holidays',
            'الاجازات',
            'العطل والاجازات الرسمية في التقويم الدراسي — academic.holidays.',
        );
    }

    public function health(): Response
    {
        return $this->shell(
            'health',
            'الصحة',
            'سجلات صحة الطالب — وحدة مخططة (medical) ضمن دورة ما بعد الإطلاق؛ هذه صفحة تشغيل أولية.',
        );
    }

    public function hr(): Response
    {
        return $this->shell(
            'hr',
            'الموارد البشرية',
            'الموظفون والعضوية المدرسية — مخطط hr (شريحة الموظفين).',
        );
    }

    public function documents(): Response
    {
        return $this->shell(
            'documents',
            'المستندات',
            'أرشيف المستندات العامة — مخطط documents.',
        );
    }

    public function communication(): Response
    {
        return $this->shell(
            'communication',
            'التواصل',
            'قوالب الرسائل والإشعارات — مخطط communication.',
        );
    }

    public function workflow(): Response
    {
        return $this->shell(
            'workflow',
            'الموافقات',
            'مسارات الاعتماد — مخطط workflow.',
        );
    }

    private function shell(string $moduleKey, string $title, string $description): Response
    {
        return Inertia::render('ops/module-shell', [
            'moduleKey' => $moduleKey,
            'title' => $title,
            'description' => $description,
        ]);
    }
}
