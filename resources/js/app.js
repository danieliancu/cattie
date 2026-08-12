import './bootstrap';
import Alpine from 'alpinejs';
import { Cat, createIcons, Heart, LockKeyhole, Map, Menu, RotateCcw, Search, ShieldCheck, ShoppingBag, UserRound, X } from 'lucide';

window.Alpine = Alpine;
Alpine.start();

createIcons({
    icons: {Cat, Heart, LockKeyhole, Map, Menu, RotateCcw, Search, ShieldCheck, ShoppingBag, UserRound, X},
});
