
import React from 'react';
import { UserData } from '../types';
import { 
  ChevronLeft, 
  Mail, 
  Calendar, 
  Wallet, 
  Globe, 
  Moon, 
  Sun,
  Info, 
  LogOut,
  ChevronRight,
  BadgeCheck
} from 'lucide-react';

interface Props {
  user: UserData;
  onLogout: () => void;
  onBack: () => void;
  theme: 'light' | 'dark';
  onToggleTheme: () => void;
}

const Profile: React.FC<Props> = ({ user, onLogout, onBack, theme, onToggleTheme }) => {
  const isDark = theme === 'dark';

  return (
    <div className={`p-6 transition-colors duration-500`}>
      <header className="relative flex items-center justify-center mb-8 h-10">
        <button 
          onClick={onBack} 
          className={`absolute left-0 p-2 -ml-2 rounded-full transition-colors ${isDark ? 'hover:bg-slate-800' : 'hover:bg-slate-100'}`}
        >
          <ChevronLeft size={24} className={isDark ? 'text-white' : 'text-slate-900'} />
        </button>
        <h1 className={`text-xl font-bold tracking-tight transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>个人中心</h1>
      </header>

      <div className="flex flex-col items-center mb-8">
        <div className="relative mb-4">
          <div className={`w-24 h-24 rounded-full flex items-center justify-center text-3xl font-bold border-4 shadow-lg transition-all ${
            isDark ? 'bg-slate-800 text-emerald-400 border-slate-900' : 'bg-emerald-500 text-white border-white'
          }`}>
            {user.email[0].toUpperCase()}
          </div>
          <div className={`absolute -bottom-1 -right-1 p-1.5 rounded-full shadow-md transition-colors ${isDark ? 'bg-slate-800' : 'bg-white'}`}>
            <BadgeCheck className={`w-5 h-5 ${isDark ? 'text-emerald-400' : 'text-emerald-500'}`} />
          </div>
        </div>
        <h2 className={`text-xl font-bold transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>{user.email}</h2>
        <span className={`mt-2 px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider transition-colors ${
          isDark ? 'bg-emerald-950/50 text-emerald-400' : 'bg-emerald-50 text-emerald-600'
        }`}>
          Premium Member
        </span>
      </div>

      <div className="space-y-6 pb-12">
        {/* Account Info */}
        <div>
          <h3 className={`text-xs font-bold uppercase tracking-widest mb-3 ml-1 transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>账号信息</h3>
          <div className={`rounded-2xl overflow-hidden border transition-all shadow-sm ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
          }`}>
            <ProfileItem icon={<Mail size={20} />} label="邮箱" value={user.email} isDark={isDark} />
            <ProfileItem icon={<Calendar size={20} />} label="到期时间" value={user.expiryDate} isDark={isDark} />
            <ProfileItem icon={<Wallet size={20} />} label="账号余额" value={`¥${user.balance.toFixed(2)}`} last isDark={isDark} />
          </div>
        </div>

        {/* Settings */}
        <div>
          <h3 className={`text-xs font-bold uppercase tracking-widest mb-3 ml-1 transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>应用设置</h3>
          <div className={`rounded-2xl overflow-hidden border transition-all shadow-sm ${
            isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
          }`}>
            <ProfileItem icon={<Globe size={20} />} label="语言设置" value="中文" clickable isDark={isDark} />
            <ProfileItem 
              icon={isDark ? <Sun size={20} /> : <Moon size={20} />} 
              label="深色模式" 
              value={isDark ? "已开启" : "已关闭"} 
              clickable 
              onClick={onToggleTheme}
              isDark={isDark} 
            />
            <ProfileItem icon={<Info size={20} />} label="关于大象网络" value="v1.0.0" clickable last isDark={isDark} />
          </div>
        </div>

        <button 
          onClick={onLogout}
          className={`w-full py-4 rounded-2xl font-bold flex items-center justify-center space-x-2 active:scale-95 transition-all ${
            isDark ? 'bg-rose-950/20 text-rose-500 hover:bg-rose-950/30' : 'bg-rose-50 text-rose-500 hover:bg-rose-100'
          }`}
        >
          <LogOut size={20} />
          <span>退出登录</span>
        </button>
      </div>
    </div>
  );
};

interface ItemProps {
  icon: React.ReactNode;
  label: string;
  value: string;
  clickable?: boolean;
  last?: boolean;
  isDark: boolean;
  onClick?: () => void;
}

const ProfileItem: React.FC<ItemProps> = ({ icon, label, value, clickable, last, isDark, onClick }) => (
  <div 
    onClick={onClick}
    className={`flex items-center justify-between p-4 transition-colors ${!last ? (isDark ? 'border-b border-slate-800' : 'border-b border-slate-50') : ''} ${clickable ? (isDark ? 'cursor-pointer active:bg-slate-800' : 'cursor-pointer active:bg-slate-50') : ''}`}
  >
    <div className="flex items-center space-x-3">
      <div className={isDark ? 'text-slate-500' : 'text-slate-400'}>{icon}</div>
      <span className={`font-medium transition-colors ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>{label}</span>
    </div>
    <div className="flex items-center space-x-2">
      <span className={`text-sm transition-colors ${isDark ? 'text-slate-500' : 'text-slate-500'}`}>{value}</span>
      {clickable && <ChevronRight size={16} className={isDark ? 'text-slate-700' : 'text-slate-300'} />}
    </div>
  </div>
);

export default Profile;
