import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/user_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _oldPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _isOldObscure = true;
  bool _isNewObscure = true;
  bool _isConfirmObscure = true;

  @override
  void dispose() {
    _oldPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final provider = context.read<UserProvider>();

    try {
      await provider.changePassword(
        _oldPasswordController.text,
        _newPasswordController.text,
      );

      if (mounted) {
        if (Navigator.canPop(context)) {
          Navigator.pop(context);
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('密码修改成功'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor:
          isDark ? AppColors.darkBackground : AppColors.lightBackground,
      appBar: AppBar(
        title: Text(
          '修改密码',
          style: AppTextStyles.titleMedium.copyWith(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
        ),
        backgroundColor:
            isDark ? AppColors.darkBackground : AppColors.lightBackground,
        surfaceTintColor: Colors.transparent,
        scrolledUnderElevation: 0,
        elevation: 0,
        iconTheme: IconThemeData(
          color:
              isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
        ),
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: SingleChildScrollView(
              padding:
                  const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
              child: Container(
                padding: const EdgeInsets.all(32.0),
                decoration: BoxDecoration(
                  color: isDark ? AppColors.darkCard : AppColors.lightCard,
                  borderRadius: AppDimensions.borderRadiusLarge,
                  border: Border.all(
                    color: isDark
                        ? AppColors.darkCardBorder
                        : AppColors.lightCardBorder,
                  ),
                ),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _buildPasswordField(
                        controller: _oldPasswordController,
                        label: '旧密码',
                        hint: '请输入旧密码',
                        isDark: isDark,
                        autofocus: true,
                        obscureText: _isOldObscure,
                        onToggleObscure: () =>
                            setState(() => _isOldObscure = !_isOldObscure),
                        validator: (v) => v?.isEmpty == true ? '请输入旧密码' : null,
                      ),
                      const SizedBox(height: 24),
                      _buildPasswordField(
                        controller: _newPasswordController,
                        label: '新密码',
                        hint: '密码（8位以上）',
                        isDark: isDark,
                        obscureText: _isNewObscure,
                        onToggleObscure: () =>
                            setState(() => _isNewObscure = !_isNewObscure),
                        validator: (v) {
                          if (v == null || v.isEmpty) return '请输入新密码';
                          if (v.length < 8) return '密码长度不能少于8位';
                          return null;
                        },
                      ),
                      const SizedBox(height: 24),
                      _buildPasswordField(
                        controller: _confirmPasswordController,
                        label: '确认新密码',
                        hint: '确认密码',
                        isDark: isDark,
                        obscureText: _isConfirmObscure,
                        onToggleObscure: () => setState(
                            () => _isConfirmObscure = !_isConfirmObscure),
                        validator: (v) {
                          if (v == null || v.isEmpty) return '请确认新密码';
                          if (v != _newPasswordController.text)
                            return '两次输入的密码不一致';
                          return null;
                        },
                      ),
                      const SizedBox(height: 48),
                      Consumer<UserProvider>(
                        builder: (context, provider, child) {
                          return SizedBox(
                            height: 50,
                            child: ElevatedButton(
                              onPressed: provider.isLoading ? null : _submit,
                              style: ElevatedButton.styleFrom(
                                backgroundColor:
                                    AppColors.getPrimaryButton(isDark),
                                shape: RoundedRectangleBorder(
                                  borderRadius:
                                      AppDimensions.borderRadiusMedium,
                                ),
                                elevation: 0,
                              ),
                              child: provider.isLoading
                                  ? const SizedBox(
                                      width: 24,
                                      height: 24,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Text(
                                      '确认修改',
                                      style: TextStyle(
                                        fontSize: 16,
                                        fontWeight: FontWeight.bold,
                                        color: Colors.white,
                                      ),
                                    ),
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPasswordField({
    required TextEditingController controller,
    required String label,
    required String hint,
    required bool isDark,
    bool autofocus = false,
    bool obscureText = true,
    VoidCallback? onToggleObscure,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTextStyles.bodyMedium.copyWith(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          autofocus: autofocus,
          obscureText: obscureText,
          style: TextStyle(
            color:
                isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(
              color: isDark
                  ? AppColors.darkTextTertiary
                  : AppColors.lightTextSecondary,
            ),
            filled: true,
            fillColor: isDark
                ? AppColors.darkInputBackground
                : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryLight : AppColors.primary,
                width: 1.5,
              ),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.errorLight : AppColors.error,
                width: 1.5,
              ),
            ),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            suffixIcon: onToggleObscure != null
                ? IconButton(
                    icon: Icon(
                      obscureText ? Icons.visibility_off : Icons.visibility,
                      color: isDark
                          ? AppColors.darkTextTertiary
                          : AppColors.lightTextSecondary,
                      size: 20,
                    ),
                    onPressed: onToggleObscure,
                  )
                : null,
          ),
          validator: validator,
        ),
      ],
    );
  }
}
