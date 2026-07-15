import CommentsManager from './pages/CommentsManager.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('comments::manager', CommentsManager);
});
