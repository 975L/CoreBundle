import { startStimulusApp } from '@symfony/stimulus-bundle';
import AiAssistantController from './js/ai-assistant.js';
import AiRephraseController from './js/ai-rephrase.js';
import BlockController from './js/block.js';
import BlockCollectionController from './js/block-collection.js';
import BlockDuplicateController from './js/block-duplicate.js';
import BlockFocusController from './js/block-focus.js';
import EaSortableController from './js/ea-sortable.js';
import FieldFocusController from './js/field-focus.js';
import FormFieldTemplateController from './js/form-field-template.js';
import LegalModelController from './js/legal-model.js';
import TitleConfirmController from './js/title-confirm.js';
import './js/trix-editor.js';
import './js/media-preview.js';
import './js/icon-picker.js';
import './js/mobile-file-accept.js';

// Back-office controllers, used only in EasyAdmin Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('aiAssistant', AiAssistantController);
app.register('aiRephrase', AiRephraseController);
app.register('block', BlockController);
app.register('blockCollection', BlockCollectionController);
app.register('blockDuplicate', BlockDuplicateController);
app.register('blockFocus', BlockFocusController);
app.register('eaSortable', EaSortableController);
app.register('fieldFocus', FieldFocusController);
app.register('formFieldTemplate', FormFieldTemplateController);
// Kebab-case on purpose: the identifier is what Stimulus derives data-legal-model-target/-value from, and a camelCase one would look for data-legalModel-* instead
app.register('legal-model', LegalModelController);
// Same kebab-case reasoning, the message being read from data-title-confirm-message-value
app.register('title-confirm', TitleConfirmController);

// Mount eaSortable, blockCollection, blockDuplicate, blockFocus, fieldFocus and formFieldTemplate on <body> automatically: EasyAdmin's layout never sets data-controller itself, so without this none of the drag-and-drop, new-block scroll/focus, duplicate-block, used-in-media-library scroll/focus, focus-a-named-field or add-field-from-template behaviors would ever connect.
document.body.setAttribute(
    'data-controller',
    [document.body.dataset.controller, 'eaSortable', 'blockCollection', 'blockDuplicate', 'blockFocus', 'fieldFocus', 'formFieldTemplate'].filter(Boolean).join(' ')
);
