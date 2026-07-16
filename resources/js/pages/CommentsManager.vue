<script setup>
import axios from 'axios';
import { computed, nextTick, ref } from 'vue';
import {
    Avatar,
    Badge,
    Button,
    Card,
    ConfirmationModal,
    DropdownItem,
    EmptyStateItem,
    Heading,
    Listing,
    Modal,
    StatusIndicator,
} from '@statamic/cms/ui';

const props = defineProps({
    cards: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    sites: {
        type: Array,
        default: () => [],
    },
    commentsEnabled: {
        type: Boolean,
        default: false,
    },
});

const cards = ref([...props.cards]);
const selectedSite = ref('all');
const expandedBlogId = ref(cards.value[0]?.blog_entry_id ?? null);
const listing = ref(null);
const selectedComment = ref(null);
const commentModalOpen = ref(false);
const commentToDelete = ref(null);
const deleteModalOpen = ref(false);
const busyAction = ref(null);

const summary = computed(() => ({
    blogs: cards.value.length,
    comments: cards.value.reduce((total, card) => total + Number(card.count || 0), 0),
    drafts: cards.value.reduce((total, card) => total + Number(card.draft_count || 0), 0),
    published: cards.value.reduce((total, card) => total + Number(card.published_count || 0), 0),
}));

const siteOptions = computed(() => {
    const grouped = new Map();

    cards.value.forEach((card) => {
        if (!grouped.has(card.site)) {
            grouped.set(card.site, {
                handle: card.site,
                label: card.site_label,
                count: 0,
            });
        }

        grouped.get(card.site).count += Number(card.count || 0);
    });

    return [...grouped.values()].sort((a, b) => a.handle.localeCompare(b.handle));
});

const filteredCards = computed(() => {
    if (selectedSite.value === 'all') {
        return cards.value;
    }

    return cards.value.filter((card) => card.site === selectedSite.value);
});

function setListingRef(el) {
    listing.value = el;

    if (!el) {
        return;
    }

    // Kit ListingSearch hardcoduje placeholder przez __('Search...') (brak propa).
    // CP tego projektu bywa w locale EN, a globalny override zmieniłby wszystkie listingi.
    // Ustawiamy placeholder po polsku lokalnie po zamontowaniu Listingu (Vue nie cofnie —
    // wartość bindingu się nie zmienia, więc nie jest re-patchowana).
    let tries = 0;
    const localizeSearch = () => {
        const input = document.getElementById('listings-search');
        if (input) {
            input.setAttribute('placeholder', 'Szukaj komentarzy');
            const label = document.querySelector('label[for="listings-search"]');
            if (label) {
                label.textContent = 'Szukaj komentarzy';
            }
            return;
        }
        if (tries++ < 10) {
            setTimeout(localizeSearch, 30);
        }
    };
    nextTick(localizeSearch);
}

function selectSite(site) {
    selectedSite.value = site;

    if (!filteredCards.value.some((card) => card.blog_entry_id === expandedBlogId.value)) {
        expandedBlogId.value = filteredCards.value[0]?.blog_entry_id ?? null;
    }
}

function toggleCard(card) {
    expandedBlogId.value = expandedBlogId.value === card.blog_entry_id ? null : card.blog_entry_id;
}

function refreshRows() {
    listing.value?.refresh();
}

function syncCards(nextCards) {
    cards.value = [...nextCards];

    if (!cards.value.some((card) => card.blog_entry_id === expandedBlogId.value)) {
        expandedBlogId.value = null;
    }
}

function showComment(row) {
    selectedComment.value = row;
    commentModalOpen.value = true;
}

function askDelete(row) {
    commentToDelete.value = row;
    deleteModalOpen.value = true;
}

async function postAction(row, url) {
    busyAction.value = `${row.id}:${url}`;

    try {
        const response = await axios.post(url);

        if (response.data.cards) {
            syncCards(response.data.cards);
        }

        if (response.data.comment && selectedComment.value?.id === response.data.comment.id) {
            selectedComment.value = response.data.comment;
        }

        Statamic.$toast.success(response.data.message || 'Zapisano zmianę.');
        refreshRows();
    } catch (error) {
        Statamic.$toast.error(error.response?.data?.message || 'Nie udało się wykonać akcji.');
    } finally {
        busyAction.value = null;
    }
}

async function confirmDelete() {
    if (!commentToDelete.value) {
        return;
    }

    const row = commentToDelete.value;
    busyAction.value = `delete:${row.id}`;

    try {
        const response = await axios.delete(row.delete_url);

        if (response.data.cards) {
            syncCards(response.data.cards);
        }

        Statamic.$toast.success(response.data.message || 'Komentarz usunięty.');
        deleteModalOpen.value = false;
        commentModalOpen.value = false;
        selectedComment.value = null;
        commentToDelete.value = null;
        refreshRows();
    } catch (error) {
        Statamic.$toast.error(error.response?.data?.message || 'Nie udało się usunąć komentarza.');
    } finally {
        busyAction.value = null;
    }
}

function actionBusy(row, action) {
    return busyAction.value === `${row.id}:${row[`${action}_url`]}`;
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <Heading text="Komentarze" />
                <p class="mt-1 max-w-3xl text-sm text-gray-700 dark:text-gray-200">
                    Panel moderacji pokazuje komentarze ze wszystkich języków jednocześnie. Nie musisz przełączać języka CP.
                </p>
            </div>

            <Badge
                :color="commentsEnabled ? 'green' : 'amber'"
                :text="commentsEnabled ? 'Komentarze na froncie włączone' : 'Komentarze na froncie wyłączone'"
                pill
            />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <Card class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-600">Wpisy z komentarzami</div>
                <div class="mt-2 text-2xl font-semibold">{{ summary.blogs }}</div>
            </Card>
            <Card class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-600">Komentarze razem</div>
                <div class="mt-2 text-2xl font-semibold">{{ summary.comments }}</div>
            </Card>
            <Card class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-600">Do moderacji</div>
                <div class="mt-2 text-2xl font-semibold">{{ summary.drafts }}</div>
            </Card>
            <Card class="p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-600">Opublikowane</div>
                <div class="mt-2 text-2xl font-semibold">{{ summary.published }}</div>
            </Card>
        </div>

        <div v-if="siteOptions.length" class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :variant="selectedSite === 'all' ? 'primary' : 'default'"
                :text="`Wszystkie języki (${summary.comments})`"
                @click="selectSite('all')"
            />
            <Button
                v-for="site in siteOptions"
                :key="site.handle"
                size="sm"
                :variant="selectedSite === site.handle ? 'primary' : 'default'"
                :text="`${site.label} (${site.count})`"
                @click="selectSite(site.handle)"
            />
        </div>

        <EmptyStateItem
            v-if="cards.length === 0"
            icon="mail-chat-bubble-text"
            text="Brak komentarzy do moderacji."
        />

        <div v-else class="space-y-3">
            <Card
                v-for="card in filteredCards"
                :key="card.blog_entry_id"
                class="overflow-hidden p-0"
            >
                <button
                    type="button"
                    class="flex w-full items-start gap-3 p-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-800"
                    :aria-expanded="expandedBlogId === card.blog_entry_id"
                    @click="toggleCard(card)"
                >
                    <svg
                        class="mt-1 size-4 shrink-0 text-gray-500 transition-transform"
                        :class="{ 'rotate-90': expandedBlogId === card.blog_entry_id }"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :text="card.site_label" size="sm" />
                            <Badge v-if="card.missing_blog" color="amber" text="Wpis usunięty" size="sm" />
                        </div>
                        <h2 class="mt-2 truncate text-base font-semibold">{{ card.title }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <Badge color="amber" :text="`Drafty: ${card.draft_count}`" size="sm" />
                            <Badge color="green" :text="`Live: ${card.published_count}`" size="sm" />
                            <span class="text-gray-600">Ostatni: {{ card.latest_at || 'brak daty' }}</span>
                        </div>
                    </div>
                    <Badge :text="String(card.count)" color="blue" pill />
                </button>

                <div
                    v-if="expandedBlogId === card.blog_entry_id"
                    class="border-t border-gray-200 dark:border-gray-700"
                >
                    <div class="flex items-center justify-end gap-2 px-4 pt-3">
                        <Button v-if="card.edit_url" size="xs" variant="subtle" text="Edytuj wpis" :href="card.edit_url" />
                        <Button v-if="card.public_url" size="xs" variant="subtle" text="Otwórz" :href="card.public_url" target="_blank" />
                        <Button size="xs" text="Odśwież" @click="refreshRows" />
                    </div>

                    <div class="p-4">
                        <Listing
                            :ref="setListingRef"
                            :url="card.rows_url"
                            :allow-bulk-actions="false"
                            :allow-presets="false"
                            :allow-customizing-columns="false"
                            sort-column="commented_at"
                            sort-direction="desc"
                            :per-page="15"
                        >
                        <template #cell-author="{ row }">
                            <div class="flex items-center gap-3" :class="{ 'ps-8': row.depth > 0 }">
                                <Avatar :user="row.avatar" />
                                <div class="min-w-0">
                                    <div class="font-medium">{{ row.author }}</div>
                                    <div class="truncate text-xs text-gray-600">{{ row.author_email || 'brak emaila' }}</div>
                                    <Badge v-if="row.depth > 0" size="sm" text="Odpowiedź" />
                                </div>
                            </div>
                        </template>

                        <template #cell-message="{ row }">
                            <button
                                type="button"
                                class="max-w-xl text-left text-sm leading-relaxed hover:text-blue-600"
                                @click="showComment(row)"
                            >
                                {{ row.message_excerpt }}
                            </button>
                        </template>

                        <template #cell-status="{ row }">
                            <StatusIndicator :status="row.status" show-label />
                        </template>

                        <template #cell-commented_at="{ row }">
                            <span class="text-sm">{{ row.commented_at_display }}</span>
                        </template>

                        <template #prepended-row-actions="{ row }">
                            <DropdownItem
                                v-if="!row.published"
                                text="Publikuj"
                                icon="checkmark"
                                @click="postAction(row, row.publish_url)"
                            />
                            <DropdownItem
                                v-else
                                text="Cofnij publikację"
                                icon="eye-slash"
                                @click="postAction(row, row.unpublish_url)"
                            />
                            <DropdownItem
                                text="Pokaż pełne dane"
                                icon="eye"
                                @click="showComment(row)"
                            />
                            <DropdownItem
                                text="Usuń"
                                icon="trash"
                                variant="destructive"
                                @click="askDelete(row)"
                            />
                        </template>
                        </Listing>
                    </div>
                </div>
            </Card>
        </div>

        <Modal
            v-model:open="commentModalOpen"
            title="Szczegóły komentarza"
            icon="mail-chat-bubble-text"
        >
            <div v-if="selectedComment" class="space-y-4">
                <div class="flex items-center gap-3">
                    <Avatar :user="selectedComment.avatar" />
                    <div>
                        <div class="font-semibold">{{ selectedComment.author }}</div>
                        <div class="text-sm text-gray-600">{{ selectedComment.author_email || 'brak emaila' }}</div>
                    </div>
                </div>

                <dl class="grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="font-medium text-gray-600">Status</dt>
                        <dd><StatusIndicator :status="selectedComment.status" show-label /></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Data</dt>
                        <dd>{{ selectedComment.commented_at_display }}</dd>
                    </div>
                </dl>

                <div>
                    <div class="mb-1 font-medium text-gray-600">Pełna treść</div>
                    <p class="whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-3 text-sm leading-relaxed dark:border-gray-700 dark:bg-gray-800">
                        {{ selectedComment.message }}
                    </p>
                </div>
            </div>

            <template #footer>
                <div v-if="selectedComment" class="flex justify-end gap-2">
                    <Button
                        v-if="!selectedComment.published"
                        variant="primary"
                        text="Publikuj"
                        :loading="actionBusy(selectedComment, 'publish')"
                        @click="postAction(selectedComment, selectedComment.publish_url)"
                    />
                    <Button
                        v-else
                        text="Cofnij publikację"
                        :loading="actionBusy(selectedComment, 'unpublish')"
                        @click="postAction(selectedComment, selectedComment.unpublish_url)"
                    />
                    <Button
                        variant="danger"
                        text="Usuń"
                        @click="askDelete(selectedComment)"
                    />
                </div>
            </template>
        </Modal>

        <ConfirmationModal
            v-model:open="deleteModalOpen"
            title="Usunąć komentarz?"
            :body-text="commentToDelete?.reply_count > 0
                ? `Usunie komentarz oraz ${commentToDelete.reply_count} odpowiedzi. Tej operacji nie można cofnąć.`
                : 'Usunie komentarz. Tej operacji nie można cofnąć.'"
            button-text="Usuń"
            cancel-text="Anuluj"
            danger
            :busy="busyAction === `delete:${commentToDelete?.id}`"
            @confirm="confirmDelete"
        />
    </div>
</template>
