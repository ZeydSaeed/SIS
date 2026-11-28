<?php

namespace App\Http\Requests\Curriculum;

/**
 * «تعديل منهج» (the «انشاء منهج دراسي» window in edit mode): the same fields as a new curriculum — year, place, name,
 * «عدد المواد», its subjects and their teachers.
 */
class UpdateCurriculumPageRequest extends CreateCurriculumPageRequest {}
