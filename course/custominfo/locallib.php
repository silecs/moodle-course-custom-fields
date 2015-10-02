<?php
class custominfo_field_extension_course implements custominfo_field_extension_i {
    protected $capability = 'moodle/course:update';

    /**
     * @return string
     */
    public function get_capability() {
        return $this->capability;
    }

    /**
     * Check if the field data is visible to the current user
     * @param object $field     StdClass object from the table custom_info_field
     * @param integer $objectid
     * @return  boolean
     */
    public function is_visible($field, $objectid) {
        global $USER;

        switch ($field->visible) {
            case CUSTOMINFO_VISIBLE_ALL:
                return true;
            case CUSTOMINFO_VISIBLE_PRIVATE:
                return has_capability('moodle/course:view', context_course::instance($objectid));
            case CUSTOMINFO_VISIBLE_NONE:
            default:
                return has_capability($this->capability, context_course::instance($objectid));
        }
    }
} /// End of class definition
