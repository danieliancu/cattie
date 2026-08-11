import './bootstrap';
import Alpine from 'alpinejs';
import { Cat, createIcons, LockKeyhole, Menu, RotateCcw, Search, ShieldCheck, ShoppingBag, UserRound, X } from 'lucide';

window.Alpine = Alpine;
Alpine.start();

createIcons({
    icons: {Cat, LockKeyhole, Menu, RotateCcw, Search, ShieldCheck, ShoppingBag, UserRound, X},
});
