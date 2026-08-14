import './bootstrap';
import './embedded-checkout';
import Alpine from 'alpinejs';
import { Cat, createIcons, Heart, LockKeyhole, Map, Menu, RotateCcw, Search, ShieldCheck, ShoppingCart, UserRound, X } from 'lucide';

window.Alpine = Alpine;
Alpine.start();

createIcons({
    icons: {Cat, Heart, LockKeyhole, Map, Menu, RotateCcw, Search, ShieldCheck, ShoppingCart, UserRound, X},
});
