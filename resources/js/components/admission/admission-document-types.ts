import { t } from '@/i18n';

/** Label of an application document type (admission.application_documents.document_type). */
export function documentTypeLabel(type: number): string {
    const i18n = t().admission;
    if (type === 1) return i18n.documentTypeId;
    if (type === 2) return i18n.documentTypeBirth;
    if (type === 3) return i18n.documentTypePhoto;
    if (type === 11) return i18n.docStudentIdFront;
    if (type === 12) return i18n.docStudentIdBack;
    if (type === 13) return i18n.docFatherIdFront;
    if (type === 14) return i18n.docFatherIdBack;
    if (type === 15) return i18n.docMotherIdFront;
    if (type === 16) return i18n.docMotherIdBack;
    if (type === 17) return i18n.docResidenceFront;
    if (type === 18) return i18n.docResidenceBack;
    if (type === 19) return i18n.docGraduationCertificate;
    return i18n.documentTypeOther;
}
