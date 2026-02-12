
import React, { useState } from 'react';
import { Mail, Lock, Eye, EyeOff, ShieldCheck, Gift, ChevronLeft } from 'lucide-react';
import Logo from './Logo';

interface Props {
  onBack: () => void;
  theme: 'light' | 'dark';
}

const Register: React.FC<Props> = ({ onBack, theme }) => {
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const handleRegister = (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setTimeout(() => {
      setIsLoading(false);
      onBack();
    }, 1500);
  };

  return (
    <div className="min-h-screen bg-white flex flex-col p-8 max-w-md mx-auto relative">
      <header className="absolute top-8 left-8">
        <button onClick={onBack} className="p-2 -ml-2 rounded-full hover:bg-slate-100 transition-colors">
          <ChevronLeft size={24} className="text-slate-900" />
        </button>
      </header>

      <div className="flex-1 flex flex-col pt-12">
        <div className="mb-10 text-center">
          <div className="w-24 h-24 flex items-center justify-center mx-auto mb-4">
             <div className="w-16 h-16">
               <Logo classNameBody="stroke-slate-900" classNameRoad="stroke-emerald-500" />
             </div>
          </div>
          <h1 className="text-3xl font-black text-slate-900 tracking-tight">创建账号</h1>
          <p className="text-slate-400 text-sm font-medium mt-2 italic">加入大象网络,连接无界</p>
        </div>

        <form onSubmit={handleRegister} className="space-y-4">
          {/* Email */}
          <div className="relative group">
            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
              <Mail size={18} />
            </div>
            <input 
              type="email" 
              placeholder="邮箱"
              className="w-full bg-slate-50 border-none rounded-2xl py-4 pl-12 pr-4 outline-none focus:ring-2 focus:ring-emerald-500/20 text-slate-700 font-medium placeholder-slate-400 transition-all"
              required
            />
          </div>

          {/* Email Verification */}
          <div className="flex space-x-3">
            <div className="relative group flex-1">
              <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                <ShieldCheck size={18} />
              </div>
              <input 
                type="text" 
                placeholder="邮箱验证码"
                className="w-full bg-slate-50 border-none rounded-2xl py-4 pl-12 pr-4 outline-none focus:ring-2 focus:ring-emerald-500/20 text-slate-700 font-medium placeholder-slate-400 transition-all"
                required
              />
            </div>
            <button 
              type="button" 
              className="bg-emerald-400 hover:bg-emerald-500 text-white font-bold px-6 rounded-2xl transition-all active:scale-95 text-sm"
            >
              发送
            </button>
          </div>

          {/* Password */}
          <div className="relative group">
            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
              <Lock size={18} />
            </div>
            <input 
              type={showPassword ? 'text' : 'password'} 
              placeholder="密码"
              className="w-full bg-slate-50 border-none rounded-2xl py-4 pl-12 pr-12 outline-none focus:ring-2 focus:ring-emerald-500/20 text-slate-700 font-medium placeholder-slate-400 transition-all"
              required
            />
            <button 
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
            >
              {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
          </div>

          {/* Confirm Password */}
          <div className="relative group">
            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
              <Lock size={18} />
            </div>
            <input 
              type={showConfirmPassword ? 'text' : 'password'} 
              placeholder="确认密码"
              className="w-full bg-slate-50 border-none rounded-2xl py-4 pl-12 pr-12 outline-none focus:ring-2 focus:ring-emerald-500/20 text-slate-700 font-medium placeholder-slate-400 transition-all"
              required
            />
            <button 
              type="button"
              onClick={() => setShowConfirmPassword(!showConfirmPassword)}
              className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
            >
              {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
          </div>

          {/* Invitation Code */}
          <div className="relative group">
            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
              <Gift size={18} />
            </div>
            <input 
              type="text" 
              placeholder="邀请码（选填）"
              className="w-full bg-slate-50 border-none rounded-2xl py-4 pl-12 pr-4 outline-none focus:ring-2 focus:ring-emerald-500/20 text-slate-700 font-medium placeholder-slate-400 transition-all"
            />
          </div>

          <button 
            type="submit"
            disabled={isLoading}
            className="w-full bg-emerald-400 hover:bg-emerald-500 text-white font-black py-4 rounded-2xl shadow-lg shadow-emerald-100 active:scale-95 transition-all mt-6"
          >
            {isLoading ? "正在注册..." : "注册"}
          </button>
        </form>

        <div className="mt-auto py-8 text-center">
          <p className="text-sm font-medium text-slate-400">
            已有账号? <button onClick={onBack} className="text-emerald-500 font-bold hover:underline">立即登录</button>
          </p>
        </div>
      </div>
    </div>
  );
};

export default Register;
