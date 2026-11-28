import {
    BedDouble,
    Briefcase,
    Calculator,
    Car,
    ChefHat,
    CircuitBoard,
    Cog,
    Cpu,
    Dumbbell,
    Factory,
    Flame,
    GraduationCap,
    Hammer,
    HardHat,
    House,
    Network,
    Plane,
    ShieldCheck,
    Smartphone,
    Snowflake,
    Sofa,
    Sprout,
    Stethoscope,
    Zap,
    type LucideIcon,
} from 'lucide-react';

/** Arabic spelling variants folded (أ/إ/آ → ا, ة → ه, ى → ي) so «الأجهزة» and «الاجهزة» match. */
function fold(text: string): string {
    return text.replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي');
}

/** First match wins: the more specific words come before the general ones (e.g. «الادارة السياحية» before «الادارة»). */
const RULES: Array<[string[], LucideIcon]> = [
    [['سيبراني', 'سبراني'], ShieldCheck],
    [['شبكات'], Network],
    [['هاتف', 'محموله'], Smartphone],
    [['تجميع', 'صيانه الحاسوب', 'حاسوب'], Cpu],
    [['طبيه', 'طبي'], Stethoscope],
    [['سياحي'], Plane],
    [['فندقي'], BedDouble],
    [['اداره'], Briefcase],
    [['بناء', 'مدني', 'عماره'], HardHat],
    [['سيارات'], Car],
    [['بتروكيمياوي', 'صناعات'], Factory],
    [['ضيافه', 'اطعمه', 'اظعمه'], ChefHat],
    [['الكترون', 'سيطره'], CircuitBoard],
    [['لحام', 'معادن'], Flame],
    [['محاسبه'], Calculator],
    [['نجاره'], Hammer],
    [['تكييف', 'تثليج', 'تبريد'], Snowflake],
    [['رياضه'], Dumbbell],
    [['زراع'], Sprout],
    [['تربيه الاسريه', 'اسريه'], House],
    [['ديكور'], Sofa],
    [['كهرباء'], Zap],
    [['ميكانيك'], Cog],
];

/** An SVG icon that says what the specialization (الاختصاص) is; a graduation cap when nothing matches. */
export function specializationIcon(name: string | null | undefined): LucideIcon {
    const text = fold(name ?? '');
    for (const [words, icon] of RULES) {
        if (words.some((word) => text.includes(fold(word)))) {
            return icon;
        }
    }

    return GraduationCap;
}
