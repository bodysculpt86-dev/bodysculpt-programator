<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

/**
 * Tests for the meta lead note helper.
 *
 * The rule is: only a non-empty note that differs from the one already mirrored into call_note
 * earns a new history entry. The editor submits its whole textarea on every save, so re-saving an
 * untouched note must not duplicate it, and an empty submission must not erase the lead's note.
 */
class MetaLeadNoteHelperTest extends TestCase
{
    public function testAFreshNoteIsRecorded(): void
    {
        $this->assertTrue(meta_lead_note_is_new(null, 'revine joi'));
    }

    public function testANoteReplacingAnEarlierOneIsRecorded(): void
    {
        $this->assertTrue(meta_lead_note_is_new('nu a răspuns', 'revine joi'));
    }

    public function testResavingTheSameNoteIsNotRecordedAgain(): void
    {
        $this->assertFalse(meta_lead_note_is_new('revine joi', 'revine joi'));
    }

    public function testSurroundingWhitespaceDoesNotMakeANoteLookNew(): void
    {
        $this->assertFalse(meta_lead_note_is_new('revine joi', "  revine joi\n"));
    }

    public function testAnEmptySubmissionIsNotRecorded(): void
    {
        $this->assertFalse(meta_lead_note_is_new('revine joi', ''));
    }

    public function testAWhitespaceOnlySubmissionIsNotRecorded(): void
    {
        $this->assertFalse(meta_lead_note_is_new('revine joi', "   \n\t "));
    }

    public function testAnEmptySubmissionDoesNotEraseANoteThatDoesNotExistYet(): void
    {
        $this->assertFalse(meta_lead_note_is_new(null, ''));
    }

    public function testANoteIsRecognisedAgainstALeadThatHasNoneYet(): void
    {
        $this->assertTrue(meta_lead_note_is_new('', 'revine joi'));
    }

    public function testAMultiLineNoteIsRecorded(): void
    {
        $this->assertTrue(meta_lead_note_is_new('revine joi', "revine joi\nla 14:00"));
    }
}
