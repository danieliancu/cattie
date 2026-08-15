import './bootstrap';
import './embedded-checkout';
import Alpine from 'alpinejs';
import { ChevronDown, createIcons, Heart, LockKeyhole, Map, Menu, RotateCcw, Search, ShieldCheck, ShoppingCart, UserRound, X } from 'lucide';

window.Alpine = Alpine;
Alpine.start();

createIcons({
    icons: {ChevronDown, Heart, LockKeyhole, Map, Menu, RotateCcw, Search, ShieldCheck, ShoppingCart, UserRound, X},
});
